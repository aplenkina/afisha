package jsonld

import (
	"context"
	"encoding/xml"
	"errors"
	"html"
	"log/slog"
	"net/http"
	"net/url"
	"regexp"
	"slices"
	"strconv"
	"strings"
	"time"
	"unicode"

	"afisha/internal/collector"
	"afisha/internal/fetch"
)

var (
	bezAnchor      = regexp.MustCompile(`(?is)<a\b[^>]*?\shref\s*=\s*(?:"([^"]+)"|'([^']+)')`)
	bezCardPath    = regexp.MustCompile(`^/[^/]+-([0-9]+)/?$`)
	bezBreadcrumbs = regexp.MustCompile(`(?is)<div\s+class="breadcrumbs"[^>]*>(.*?)</div>`)
	bezTitle       = regexp.MustCompile(`(?is)<h1\s+class="activity-name"[^>]*>(.*?)</h1>`)
	bezAddress     = regexp.MustCompile(`(?is)<div\s+class="address_activity"[^>]*>(.*?)</div>`)
	bezCalendar    = regexp.MustCompile(`(?is)<div\s+class="add-calendar"[^>]*>(.*?)(?:<div\b|</div>)`)
	bezPrice       = regexp.MustCompile(`(?is)<div\s+class="price"[^>]*>(.*?)</div>`)
	bezNotice      = regexp.MustCompile(`(?is)<div\s+class="notify-transfer-cancel[^"]*"[^>]*>(.*?)</div>`)
	bezTags        = regexp.MustCompile(`(?s)<[^>]+>`)
	bezPriceNumber = regexp.MustCompile(`[0-9]+(?:[ ][0-9]{3})*(?:[.,][0-9]{1,2})?`)
	bezDate        = regexp.MustCompile(`([0-9]{1,2})(?:\s*[—–-]\s*[0-9]{1,2})?\s+(января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря)\s+([0-9]{4})`)
	bezTime        = regexp.MustCompile(`\b[0-9]{1,2}:[0-9]{2}\b`)
	bezRange       = regexp.MustCompile(`\b([0-9]{1,2}:[0-9]{2})\s*[—–-]\s*([0-9]{1,2}:[0-9]{2})\b`)
	bezMonths      = []string{"января", "февраля", "марта", "апреля", "мая", "июня", "июля", "августа", "сентября", "октября", "ноября", "декабря"}
)

// BezKassira discovers cards in the public afisha and sitemap and reads the event on each card.
type BezKassira struct {
	*Collector
	knownURLs func(context.Context, bool) ([]string, error)
}

func NewBezKassira(name, baseURL, cityName string, cfg Config, client *fetch.Client, ua string, log *slog.Logger, knownURLs func(context.Context, bool) ([]string, error)) *BezKassira {
	return &BezKassira{Collector: New("bezkassira", name, baseURL, cityName, cfg, client, ua, log), knownURLs: knownURLs}
}

func (b *BezKassira) Collect(ctx context.Context) (collector.Result, error) {
	return b.collect(ctx, false)
}

func (b *BezKassira) CollectRecent(ctx context.Context) (collector.Result, error) {
	return b.collect(ctx, true)
}

func (b *BezKassira) collect(ctx context.Context, recent bool) (collector.Result, error) {
	var res collector.Result
	base, err := url.Parse(b.baseURL)
	if err != nil || base.Host == "" || (base.Scheme != "http" && base.Scheme != "https") {
		return res, errors.New("некорректный адрес BezKassira")
	}
	if b.knownURLs == nil {
		return res, errors.New("не настроено чтение известных карточек BezKassira")
	}
	knownURLs, err := b.knownURLs(ctx, recent)
	if err != nil {
		return res, err
	}
	if recent && len(knownURLs) == 0 {
		return res, nil
	}
	rules, err := b.client.Robots(ctx, b.baseURL, b.userAgent)
	if err != nil {
		return res, err
	}
	paths := map[string]bool{}
	add := func(raw string, known bool) {
		link := bezLink(base, raw)
		if link == nil || !bezCardPath.MatchString(link.Path) {
			return
		}
		path := strings.TrimSuffix(link.EscapedPath(), "/") + "/"
		paths[path] = paths[path] || known
	}
	for _, u := range knownURLs {
		add(u, true)
	}

	if !recent {
		for _, path := range append(slices.Clone(b.cfg.Pages), "/sitemap.xml") {
			res.Stats.PagesTotal++
			if rules != nil && !rules.Test(path) {
				res.Stats.PagesBlocked++
				continue
			}
			body, err := b.client.Get(ctx, b.baseURL+path)
			if err != nil {
				res.Stats.PagesFailed++
				b.log.Warn("не загрузился список BezKassira", "page", path, "err", err)
				continue
			}
			if path == "/sitemap.xml" {
				var sitemap struct {
					URLs []string `xml:"url>loc"`
				}
				if err := xml.Unmarshal(body, &sitemap); err != nil || len(sitemap.URLs) == 0 {
					res.Stats.PagesFailed++
					b.log.Warn("не прочитался sitemap BezKassira", "err", err)
					continue
				}
				for _, u := range sitemap.URLs {
					add(u, false)
				}
			} else {
				for _, anchor := range bezAnchor.FindAllSubmatch(body, -1) {
					add(firstNonEmpty(string(anchor[1]), string(anchor[2])), false)
				}
			}
			res.Stats.PagesLoaded++
		}
	}
	ordered := make([]string, 0, len(paths))
	for path := range paths {
		ordered = append(ordered, path)
	}
	slices.Sort(ordered)
	seen := map[string]bool{}
	unavailable := make(map[string]collector.Event)
	for index, path := range ordered {
		if index%50 == 0 {
			b.log.Info("проверка карточек BezKassira", "checked", index, "total", len(ordered))
		}
		id := bezCardPath.FindStringSubmatch(path)[1]
		if seen[id] {
			continue
		}
		if err := ctx.Err(); err != nil {
			return res, err
		}
		if rules != nil && !rules.Test(path) {
			res.Stats.PagesBlocked++
			continue
		}
		body, err := b.client.Get(ctx, b.baseURL+path)
		if err != nil {
			res.Stats.DetailsFailed++
			var status *fetch.StatusError
			if errors.As(err, &status) && status.Code == http.StatusForbidden {
				return res, err
			}
			b.log.Warn("не загрузилась карточка BezKassira", "page", path, "err", err)
			if paths[path] {
				unavailable[id] = b.unavailable(path, "Карточка временно недоступна")
			}
			continue
		}
		event, err := b.parseCard(base, path, body, paths[path], time.Now())
		if err != nil {
			res.Stats.Broken++
			b.log.Warn("не прочиталась карточка BezKassira", "page", path, "err", err)
			if paths[path] {
				unavailable[id] = b.unavailable(path, "Данные карточки не удалось прочитать")
			}
			continue
		}
		if event != nil && !seen[event.ExternalID] {
			seen[event.ExternalID] = true
			res.Events = append(res.Events, *event)
		}
	}
	for id, event := range unavailable {
		if !seen[id] {
			res.Events = append(res.Events, event)
		}
	}
	if !recent && res.Stats.PagesLoaded == 0 && len(res.Events) == 0 {
		return res, errors.New("не удалось прочитать ни одного раздела BezKassira")
	}
	if len(res.Events) == 0 && len(ordered) > 0 && res.Stats.DetailsFailed == len(ordered) {
		return res, errors.New("не загрузилась ни одна карточка BezKassira")
	}
	return res, nil
}

func (b *BezKassira) unavailable(path, reason string) collector.Event {
	return collector.Event{
		ExternalID: bezCardPath.FindStringSubmatch(path)[1],
		Observation: &collector.Observation{
			CheckedAt: time.Now().UTC(), URL: b.baseURL + path,
			Status: collector.ObservedUnavailable, CheckError: reason,
		},
	}
}

func bezLink(base *url.URL, raw string) *url.URL {
	target, err := url.Parse(html.UnescapeString(strings.TrimSpace(raw)))
	if err != nil {
		return nil
	}
	target = base.ResolveReference(target)
	if target.Scheme != base.Scheme || target.Host != base.Host || target.User != nil {
		return nil
	}
	target.RawQuery, target.Fragment = "", ""
	return target
}

func bezText(pattern *regexp.Regexp, page []byte) string {
	m := pattern.FindSubmatch(page)
	if len(m) == 0 {
		return ""
	}
	return strings.Join(strings.Fields(html.UnescapeString(bezTags.ReplaceAllString(string(m[1]), " "))), " ")
}

func (b *BezKassira) parseCard(base *url.URL, path string, page []byte, known bool, checked time.Time) (*collector.Event, error) {
	slug := strings.Trim(path, "/")
	id := bezCardPath.FindStringSubmatch(path)[1]
	obs := &collector.Observation{
		CheckedAt: checked.UTC(), URL: b.baseURL + path,
		DisplayedDate: bezText(bezCalendar, page), DisplayedPrice: bezText(bezPrice, page),
		DisplayedTitle: bezText(bezTitle, page), DisplayedAddress: bezText(bezAddress, page),
		Notice: bezText(bezNotice, page),
	}
	var raw ldEvent
	for _, script := range ldJSON.FindAllSubmatch(page, -1) {
		for _, node := range eventNodes(graphNodes(script[1])) {
			candidate, err := decode(string(node))
			if err != nil || !strings.HasSuffix(candidate.Type, "Event") || candidate.URL == "" {
				continue
			}
			if link := bezLink(base, candidate.URL); link != nil && strings.Trim(link.Path, "/") == slug {
				raw = candidate
				break
			}
		}
		if raw.URL != "" {
			break
		}
	}
	obs.SourceStatus = raw.Status
	obs.Status = bezStatus(raw.Status, raw.Offers.Availability, obs.Notice)
	if raw.URL == "" {
		if known && (obs.Status == collector.ObservedCancelled || obs.Status == collector.ObservedPostponed) {
			return &collector.Event{ExternalID: id, SourceURL: obs.URL, Observation: obs}, nil
		}
		if known {
			return nil, errors.New("пропала разметка основной карточки")
		}
		return nil, nil
	}

	obs.StartsAt, obs.EndsAt = raw.StartDate, raw.EndDate
	obs.Title = strings.TrimSpace(raw.Name)
	obs.Venue, obs.Address = raw.Location.Name, raw.Location.Address.Street
	obs.Availability = raw.Offers.Availability
	if !obs.HasConfirmedSession() {
		if !known {
			return nil, nil
		}
		return &collector.Event{ExternalID: id, SourceURL: obs.URL, Observation: obs}, nil
	}
	if !strings.EqualFold(strings.TrimSpace(raw.Location.Address.Locality), b.cityName) {
		if known {
			obs.Status = collector.ObservedReview
			obs.Issues = append(obs.Issues, "В карточке изменился или пропал город: "+raw.Location.Address.Locality)
			return &collector.Event{ExternalID: id, SourceURL: obs.URL, Observation: obs}, nil
		}
		return nil, nil
	}
	var category string
	if block := bezBreadcrumbs.FindSubmatch(page); len(block) > 0 {
		for _, a := range bezAnchor.FindAllSubmatch(block[1], -1) {
			if link := bezLink(base, firstNonEmpty(string(a[1]), string(a[2]))); link != nil {
				if alias := b.cfg.Categories[strings.Trim(link.Path, "/")]; alias != "" {
					category = alias
					break
				}
			}
		}
	}
	if category == "" {
		if known {
			return nil, errors.New("карточка больше не относится к настроенным разделам")
		}
		return nil, nil
	}
	e, err := toEvent(raw, nil)
	if err != nil {
		return nil, err
	}
	if !strings.Contains(raw.StartDate, "T") || e.VenueName == "" || e.Address == "" {
		return nil, errors.New("нет точных даты или места")
	}
	e.ExternalID, e.Slug = id, "bezkassira-"+id
	e.SourceURL, e.CategoryAlias, e.Observation = obs.URL, category, obs
	e.VenueName = strings.TrimSpace(raw.Location.Name)
	if strings.EqualFold(e.VenueName, "Эхо Двор") {
		e.VenueName = "ЭХО Двор"
	} else if strings.Contains(strings.ToLower(e.VenueName), "пространство") && strings.HasSuffix(strings.Trim(e.VenueName, "\"«» "), "ЭХО") {
		e.VenueName = "ЭХО"
	}
	e.CityHints = []string{b.cityName}

	if e.EndsAt != nil && !e.EndsAt.After(e.StartsAt) {
		e.EndsAt = nil
		for _, span := range bezRange.FindAllStringSubmatch(html.UnescapeString(raw.Description), -1) {
			if bezClock(span[1]) == e.StartsAt.In(minsk).Format("15:04") && bezClock(span[2]) != bezClock(span[1]) {
				obs.Issues = append(obs.Issues, "В описании указано другое время окончания: "+span[2])
				break
			}
		}
	}
	if e.EndsAt != nil && e.EndsAt.Sub(e.StartsAt) > 7*24*time.Hour {
		if known {
			obs.Status = collector.ObservedReview
			obs.Issues = append(obs.Issues, "Карточка указывает длительный период, проверьте расписание")
			return &collector.Event{ExternalID: id, SourceURL: obs.URL, Observation: obs}, nil
		}
		return nil, nil
	}
	if e.StartsAt.Before(checked) && (e.EndsAt == nil || e.EndsAt.Before(checked)) {
		return nil, nil
	}
	if obs.DisplayedTitle != "" && strings.TrimSuffix(bezIdentity(obs.DisplayedTitle), " в минске") != bezIdentity(obs.Title) &&
		bezIdentity(obs.DisplayedTitle) != bezIdentity(obs.Title) {
		obs.Status = collector.ObservedReview
		obs.Issues = append(obs.Issues, "Название в анонсе расходится с разметкой")
	}
	if obs.DisplayedAddress != "" {
		displayed := strings.TrimSuffix(bezIdentity(obs.DisplayedAddress), " минск")
		if displayed != bezIdentity(obs.Address) {
			obs.Status = collector.ObservedReview
			obs.Issues = append(obs.Issues, "Адрес в анонсе расходится с разметкой")
		}
	}

	if date := bezDate.FindStringSubmatch(obs.DisplayedDate); date != nil {
		start := e.StartsAt.In(minsk)
		day, _ := strconv.Atoi(date[1])
		year, _ := strconv.Atoi(date[3])
		if start.Day() != day || start.Year() != year || int(start.Month()) != slices.Index(bezMonths, date[2])+1 {
			obs.Status = collector.ObservedReview
			obs.Issues = append(obs.Issues, "Дата в анонсе расходится с разметкой")
		}
	}
	if clock := bezTime.FindString(obs.DisplayedDate); clock != "" && bezClock(clock) != e.StartsAt.In(minsk).Format("15:04") {
		obs.Status = collector.ObservedReview
		obs.Issues = append(obs.Issues, "Время начала в анонсе расходится с разметкой")
	}

	obs.PriceMin, obs.PriceMax = e.PriceMin, e.PriceMax
	e.PriceMin, e.PriceMax, e.IsFree = nil, nil, false
	if raw.Offers.Currency == "BYN" {
		e.PriceMin, e.PriceMax = obs.PriceMin, obs.PriceMax
	}
	if strings.HasPrefix(obs.DisplayedPrice, "Цена билетов:") {
		prices := bezPriceNumber.FindAllString(obs.DisplayedPrice, 3)
		if len(prices) == 1 || len(prices) == 2 {
			priceMin := bezPriceValue(prices[0])
			priceMax := ""
			if len(prices) == 2 {
				priceMax = bezPriceValue(prices[1])
			}
			if obs.PriceMin != nil && (priceMin != bezPriceValue(*obs.PriceMin) || priceMax != bezPriceValue(firstPointer(obs.PriceMax))) {
				obs.Issues = append(obs.Issues, "Цена в карточке отличается от цены в разметке")
			}
			e.PriceMin, e.PriceMax = &priceMin, nil
			if priceMax != "" {
				e.PriceMax = &priceMax
			}
		}
	}
	if link := bezLink(base, raw.Offers.URL); raw.Offers.URL == "" || link == nil || strings.Trim(link.Path, "/") != slug+"/buy" {
		e.TicketURL = obs.URL
	} else {
		e.TicketURL = link.String()
	}
	e.IsFree = e.PriceMin != nil && bezPriceValue(*e.PriceMin) == "0.00"
	_, startErr := time.Parse(time.RFC3339, raw.StartDate)
	obs.VerifiedForPublication = startErr == nil && obs.DisplayedTitle != "" && obs.HasConfirmedSession() && len(obs.Issues) == 0
	return &e, nil
}

func bezIdentity(value string) string {
	withSpaces := strings.Map(func(r rune) rune {
		if unicode.IsLetter(r) || unicode.IsNumber(r) {
			if r == 'Ё' || r == 'ё' {
				return 'е'
			}
			return unicode.ToLower(r)
		}
		return ' '
	}, html.UnescapeString(value))
	return strings.Join(strings.Fields(withSpaces), " ")
}

func bezClock(s string) string {
	if len(s) == 4 {
		return "0" + s
	}
	return s
}

func bezPriceValue(s string) string {
	value := strings.ReplaceAll(strings.ReplaceAll(s, ",", "."), " ", "")
	if f, err := strconv.ParseFloat(value, 64); err == nil {
		return strconv.FormatFloat(f, 'f', 2, 64)
	}
	return ""
}

func firstPointer(s *string) string {
	if s == nil {
		return ""
	}
	return *s
}

func bezStatus(status, availability, notice string) string {
	notice = strings.ToLower(notice)
	switch {
	case strings.HasSuffix(status, "EventCancelled"), strings.Contains(notice, "мероприятие отменено"):
		return collector.ObservedCancelled
	case strings.HasSuffix(status, "EventPostponed"), strings.Contains(notice, "перенесен"), strings.Contains(notice, "перенесён"):
		return collector.ObservedPostponed
	case strings.HasSuffix(status, "EventMovedOnline"), strings.HasSuffix(status, "EventRescheduled"), notice != "":
		return collector.ObservedReview
	case strings.HasSuffix(availability, "SoldOut"):
		return collector.ObservedSoldOut
	default:
		return collector.ObservedScheduled
	}
}
