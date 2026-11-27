package runner

import (
	"context"
	"fmt"
	"log/slog"
	"time"

	"afisha/internal/collector"
	"afisha/internal/notify"
	"afisha/internal/store"
)

// Store — то, что раннеру нужно от базы. Интерфейс позволяет тестировать раннер без Postgres.
type Store interface {
	Cities(ctx context.Context) (map[string]int, error)
	Categories(ctx context.Context) (map[string]int, error)
	CategoryNames(ctx context.Context) (map[string]string, error)
	Sources(ctx context.Context) ([]store.SourceRow, error)
	Save(ctx context.Context, sourceID, cityID, categoryID int, e collector.Event) (bool, error)
	RecordRun(ctx context.Context, sourceID int, status, summary string) error
}

// Итоги запуска источника, которые видны в админке.
const (
	StatusOK    = "ok"
	StatusEmpty = "empty" // источник вернул 0 событий
	StatusError = "error"
)

// Factory собирает сборщик по настройкам источника из базы. cityName — город источника.
type Factory func(src store.SourceRow, cityName string) (collector.Collector, error)

type Runner struct {
	Store Store
	// Custom — сборщики, написанные под конкретный источник (ключ — slug). Они главнее настроек.
	Custom map[string]collector.Collector
	// Factory создаёт универсальный сборщик для источников с настройками в source.config.
	Factory  Factory
	Notifier notify.Notifier
	Log      *slog.Logger

	// Loc — часовой пояс для дат в сообщении (по умолчанию UTC), Now — часы для тестов.
	Loc *time.Location
	Now func() time.Time
}

func (r *Runner) now() time.Time {
	if r.Now != nil {
		return r.Now()
	}
	return time.Now()
}

// RunOnce обходит включённые источники из таблицы source и в конце присылает одну сводку.
// Падение одного источника не мешает остальным.
func (r *Runner) RunOnce(ctx context.Context) {
	r.run(ctx, false, "")
}

func (r *Runner) RefreshRecent(ctx context.Context) {
	r.run(ctx, true, "")
}

// RunSourceOnce runs one explicitly selected source independently of its schedule.
func (r *Runner) RunSourceOnce(ctx context.Context, slug string) bool {
	return r.run(ctx, false, slug)
}

type recentCollector interface {
	CollectRecent(context.Context) (collector.Result, error)
}

func (r *Runner) run(ctx context.Context, recent bool, onlySource string) bool {
	started := r.now()

	cities, err := r.Store.Cities(ctx)
	if err != nil {
		r.Notifier.Send(ctx, "Сборщики: не прочитались города: "+err.Error())
		return false
	}
	categories, err := r.Store.Categories(ctx)
	if err != nil {
		r.Notifier.Send(ctx, "Сборщики: не прочитались категории: "+err.Error())
		return false
	}
	categoryNames, err := r.Store.CategoryNames(ctx)
	if err != nil {
		r.Log.Warn("названия категорий не прочитались, в сводке будут коды", "err", err)
		categoryNames = nil
	}
	sources, err := r.Store.Sources(ctx)
	if err != nil {
		r.Notifier.Send(ctx, "Сборщики: не прочитались источники: "+err.Error())
		return false
	}

	cityNames := make(map[int]string, len(cities))
	for name, id := range cities {
		cityNames[id] = name
	}

	sum := Summary{At: started, Loc: r.Loc, CategoryNames: categoryNames}

	for _, src := range sources {
		log := r.Log.With("source", src.Slug)

		if (recent && src.Slug != "bezkassira") || (onlySource != "" && src.Slug != onlySource) {
			continue
		}
		if !src.Available && onlySource == "" {
			log.Info("источник выключен в админке, пропускаем")
			sum.Disabled++
			continue
		}

		c, report, ok := r.collectorFor(ctx, src, cityNames[src.CityID], recent)
		if !ok {
			if report == nil {
				sum.NoCollector++
			} else {
				sum.Reports = append(sum.Reports, *report)
			}
			continue
		}

		sum.Reports = append(sum.Reports, r.runCollector(ctx, log, src, c, cities, categories, recent))
	}

	sum.Took = r.now().Sub(started)

	// Если запускать было нечего (всё выключено или без настроек), присылать нечего.
	if len(sum.Reports) > 0 && (!recent || !sum.healthy()) {
		r.Notifier.Send(ctx, sum.Format())
	}
	if len(sum.Reports) == 0 {
		if onlySource != "" {
			r.Log.Error("нет настроенного сборщика для источника", "source", onlySource)
		}
		return false
	}
	for _, report := range sum.Reports {
		if report.Status != StatusOK || report.Failed > 0 {
			return false
		}
	}
	return true
}

// collectorFor подбирает сборщик. Если не вышло: report == nil — настроек нет совсем (просто пропускаем),
// report != nil — настройки есть, но некорректны (попадает в сводку как ошибка).
func (r *Runner) collectorFor(ctx context.Context, src store.SourceRow, cityName string, recent bool) (collector.Collector, *SourceReport, bool) {
	if c, ok := r.Custom[src.Slug]; ok {
		return c, nil, true
	}
	if src.Config == "" || r.Factory == nil {
		r.Log.Warn("для источника нет сборщика: заполните настройки в админке", "source", src.Slug)
		return nil, nil, false
	}

	c, err := r.Factory(src, cityName)
	if err != nil {
		msg := "некорректные настройки сборщика: " + err.Error()
		if !recent {
			r.record(ctx, src, StatusError, msg)
		}
		return nil, &SourceReport{Slug: src.Slug, Name: src.Name, Status: StatusError, Err: msg}, false
	}
	return c, nil, true
}

func (r *Runner) runCollector(ctx context.Context, log *slog.Logger, src store.SourceRow, c collector.Collector, cities, categories map[string]int, recent bool) SourceReport {
	started := r.now()
	rep := SourceReport{Slug: src.Slug, Name: src.Name, NewByCategory: map[string]int{}}
	finish := func() SourceReport {
		rep.Took = r.now().Sub(started)
		return rep
	}

	collect := c.Collect
	if recent {
		collector, ok := c.(recentCollector)
		if !ok {
			rep.Status = StatusOK
			return finish()
		}
		collect = collector.CollectRecent
	}
	res, err := collect(ctx)
	rep.Stats = res.Stats
	if err != nil {
		rep.Status, rep.Err = StatusError, "сборщик упал: "+err.Error()
		if !recent {
			r.record(ctx, src, StatusError, rep.Err)
		}
		return finish()
	}
	if len(res.Events) == 0 {
		if res.AllowEmpty {
			rep.Status = StatusOK
			summary := fmt.Sprintf("предстоящих событий нет, прошедших %d", res.Stats.Past)
			log.Info(summary)
			if !recent {
				r.record(ctx, src, StatusOK, summary)
			}
			return finish()
		}
		if recent && res.Stats.DetailsFailed == 0 && res.Stats.Broken == 0 && res.Stats.PagesBlocked == 0 {
			rep.Status = StatusOK
			return finish()
		}
		rep.Status = StatusEmpty
		if !recent {
			r.record(ctx, src, StatusEmpty, "источник вернул 0 событий")
		}
		return finish()
	}

	rep.Collected = len(res.Events)
	for _, e := range res.Events {
		cityID, ok := matchCity(cities, e.CityHints)
		stateOnly := e.Observation != nil && e.Title == ""
		if stateOnly {
			cityID, ok = src.CityID, true
		}
		if !ok {
			log.Debug("город не найден", "hints", e.CityHints, "title", e.Title)
			rep.NoCity++
			continue
		}
		categoryID, ok := categories[e.CategoryAlias]
		if !ok && !stateOnly {
			log.Warn("категории нет в базе", "alias", e.CategoryAlias, "title", e.Title)
			rep.NoCategory++
			continue
		}

		created, err := r.Store.Save(ctx, src.ID, cityID, categoryID, e)
		switch {
		case err != nil:
			log.Error("событие не сохранилось", "external_id", e.ExternalID, "err", err)
			rep.Failed++
		case created:
			rep.Created++
			rep.NewByCategory[e.CategoryAlias]++
			rep.New = append(rep.New, NewEvent{Title: e.Title, StartsAt: e.StartsAt, Place: place(e)})
		default:
			rep.Updated++
		}
	}

	line := fmt.Sprintf("собрано %d, новых %d, обновлено %d, пропущено %d, ошибок %d",
		rep.Collected, rep.Created, rep.Updated, rep.NoCity+rep.NoCategory, rep.Failed)
	if src.Slug == "bezkassira" {
		review := 0
		for _, e := range res.Events {
			if o := e.Observation; o != nil && (len(o.Issues) > 0 || !o.HasConfirmedSession()) {
				review++
			}
		}
		line += fmt.Sprintf(", на проверку %d, карточек не загрузилось %d", review, rep.Stats.DetailsFailed)
	}
	log.Info("сборщик завершён", "took", r.now().Sub(started).Round(time.Second), "summary", line)

	if rep.Created+rep.Updated == 0 {
		rep.Status, rep.Err = StatusError, "ничего не сохранилось: "+line
		if !recent {
			r.record(ctx, src, StatusError, rep.Err)
		}
		return finish()
	}

	rep.Status = StatusOK
	if !recent {
		r.record(ctx, src, StatusOK, line)
	}
	return finish()
}

func (r *Runner) record(ctx context.Context, src store.SourceRow, status, summary string) {
	if err := r.Store.RecordRun(ctx, src.ID, status, summary); err != nil {
		r.Log.Error("итог запуска не записался", "source", src.Slug, "err", err)
	}
}

// place — «Город, Площадка» для списка новых событий.
func place(e collector.Event) string {
	var parts []string
	if len(e.CityHints) > 0 && e.CityHints[0] != "" {
		parts = append(parts, upperFirst(e.CityHints[0]))
	}
	if e.VenueName != "" {
		parts = append(parts, e.VenueName)
	}
	return joinParts(parts)
}

func joinParts(p []string) string {
	out := ""
	for i, s := range p {
		if i > 0 {
			out += ", "
		}
		out += s
	}
	return out
}

func matchCity(cities map[string]int, hints []string) (int, bool) {
	for _, h := range hints {
		if id, ok := cities[store.NormalizeName(h)]; ok {
			return id, true
		}
	}
	return 0, false
}
