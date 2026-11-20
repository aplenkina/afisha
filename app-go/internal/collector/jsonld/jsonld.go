// Package jsonld — универсальный сборщик: читает schema.org/Event из JSON-LD на страницах списка.
// Страницы и соответствие разделов категориям берутся из source.config, кода на источник не нужно.
package jsonld

import (
	"context"
	"crypto/sha1"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"net/url"
	"regexp"
	"strings"
	"time"

	"github.com/temoto/robotstxt"

	"afisha/internal/collector"
	"afisha/internal/fetch"
)

var ldJSON = regexp.MustCompile(`(?is)<script[^>]*ld\+json[^>]*>(.*?)</script>`)
var controlChars = regexp.MustCompile(`[\x00-\x1f]`)
var descriptionSpan = regexp.MustCompile(`(?s)"description"\s*:.*?(,\s*"offers"|\}\s*$)`)

// Config — содержимое source.config.
type Config struct {
	// Pages — пути страниц со списком событий относительно адреса источника, например "/", "/concerts/".
	Pages []string `json:"pages"`
	// Categories — раздел URL или первый сегмент пути события -> alias категории.
	Categories map[string]string `json:"categories"`
	// CityFallback — событие без города в разметке привязывать к городу источника.
	CityFallback     bool   `json:"city_fallback"`
	DetailPathPrefix string `json:"detail_path_prefix"`
}

func ParseConfig(raw []byte) (Config, error) {
	var cfg Config
	if err := json.Unmarshal(raw, &cfg); err != nil {
		return cfg, fmt.Errorf("настройки не разобрались: %w", err)
	}
	if len(cfg.Pages) == 0 {
		return cfg, errors.New("в настройках нет pages")
	}
	if len(cfg.Categories) == 0 {
		return cfg, errors.New("в настройках нет categories: без них все события будут пропущены")
	}
	for _, p := range cfg.Pages {
		if !strings.HasPrefix(p, "/") || strings.HasPrefix(p, "//") {
			return cfg, fmt.Errorf("страница %q должна быть путём вида /afisha/ на сайте источника", p)
		}
	}
	return cfg, nil
}

type Collector struct {
	slug, name, baseURL string
	cityName            string
	cfg                 Config
	client              *fetch.Client
	userAgent           string
	log                 *slog.Logger
}

// New: cityName — название города источника, нужно для city_fallback.
func New(slug, name, baseURL, cityName string, cfg Config, client *fetch.Client, userAgent string, log *slog.Logger) *Collector {
	return &Collector{
		slug: slug, name: name, baseURL: strings.TrimRight(baseURL, "/"),
		cityName: cityName, cfg: cfg, client: client, userAgent: userAgent, log: log,
	}
}

func (c *Collector) Slug() string    { return c.slug }
func (c *Collector) Name() string    { return c.name }
func (c *Collector) BaseURL() string { return c.baseURL }

// robots возвращает правила для нашего бота. Нет файла — можно всё; любая другая ошибка — не собираем вслепую.
func (c *Collector) robots(ctx context.Context) (*robotstxt.Group, error) {
	body, err := c.client.Get(ctx, c.baseURL+"/robots.txt")
	if err != nil {
		var se *fetch.StatusError
		if errors.As(err, &se) && (se.Code == http.StatusNotFound || se.Code == http.StatusGone) {
			return nil, nil
		}
		return nil, fmt.Errorf("robots.txt не прочитался: %w", err)
	}
	r, err := robotstxt.FromBytes(body)
	if err != nil {
		return nil, fmt.Errorf("robots.txt не разобрался: %w", err)
	}
	return r.FindGroup(c.userAgent), nil
}

func (c *Collector) Collect(ctx context.Context) (collector.Result, error) {
	var res collector.Result
	res.Stats.PagesTotal = len(c.cfg.Pages)

	rules, err := c.robots(ctx)
	if err != nil {
		return res, err
	}

	seen := map[string]bool{}
	visited := map[string]bool{}
	now := time.Now()
	var lastErr error

	for _, p := range c.cfg.Pages {
		if rules != nil && !rules.Test(p) {
			c.log.Warn("страница закрыта в robots.txt, пропускаем", "source", c.slug, "page", p)
			res.Stats.PagesBlocked++
			continue
		}

		body, err := c.client.Get(ctx, c.baseURL+p)
		if err != nil {
			c.log.Warn("страница не загрузилась", "source", c.slug, "page", p, "err", err)
			res.Stats.PagesFailed++
			lastErr = err
			continue
		}
		res.Stats.PagesLoaded++

		section := strings.TrimPrefix(strings.Trim(p, "/"), "ru/")
		alias := c.cfg.Categories[section]
		events, stats := Parse(body, now, c.cfg)
		if c.cfg.DetailPathPrefix != "" && alias != "" {
			base, err := url.Parse(c.baseURL)
			if err != nil {
				return res, err
			}
			for _, itemURL := range listItemURLs(body) {
				link, err := url.Parse(itemURL)
				if err != nil {
					continue
				}
				link = base.ResolveReference(link)
				if link.Host != base.Host || link.Scheme != base.Scheme || !strings.HasPrefix(link.Path, c.cfg.DetailPathPrefix) {
					continue
				}
				path := link.EscapedPath()
				if visited[path] || (rules != nil && !rules.Test(path)) {
					continue
				}
				visited[path] = true
				detail, err := c.client.Get(ctx, c.baseURL+path)
				if err != nil {
					if ctx.Err() != nil {
						return res, ctx.Err()
					}
					c.log.Warn("карточка не загрузилась", "source", c.slug, "page", path, "err", err)
					continue
				}
				items, detailStats := parseEvents(detail, now, c.cfg, alias)
				events = append(events, items...)
				stats.Past += detailStats.Past
				stats.NoCategory += detailStats.NoCategory
				stats.Broken += detailStats.Broken
			}
		}
		c.log.Info("страница разобрана", "source", c.slug, "page", p,
			"events", len(events), "no_category", stats.NoCategory, "past", stats.Past, "broken", stats.Broken)
		res.Stats.Past += stats.Past
		res.Stats.NoCategory += stats.NoCategory
		res.Stats.Broken += stats.Broken

		for _, e := range events {
			if c.cfg.CityFallback && c.cityName != "" {
				e.CityHints = append(e.CityHints, c.cityName)
			}
			if !seen[e.ExternalID] {
				seen[e.ExternalID] = true
				res.Events = append(res.Events, e)
			}
		}
	}

	if res.Stats.PagesLoaded == 0 && lastErr != nil {
		return res, lastErr
	}
	return res, nil
}

type Stats struct {
	NoCategory int
	Past       int
	Broken     int
}

func graphNodes(raw []byte) []json.RawMessage {
	var graph struct {
		Nodes []json.RawMessage `json:"@graph"`
	}
	if json.Unmarshal(raw, &graph) == nil && graph.Nodes != nil {
		return graph.Nodes
	}
	return []json.RawMessage{raw}
}

func listItemURLs(page []byte) []string {
	var urls []string
	for _, script := range ldJSON.FindAllSubmatch(page, -1) {
		for _, node := range graphNodes(script[1]) {
			var list struct {
				Type  string `json:"@type"`
				Items []struct {
					URL string `json:"url"`
				} `json:"itemListElement"`
			}
			if json.Unmarshal(node, &list) != nil || list.Type != "ItemList" {
				continue
			}
			for _, item := range list.Items {
				urls = append(urls, item.URL)
			}
		}
	}
	return urls
}

type ldEvent struct {
	Status    string `json:"eventStatus"`
	Type      string `json:"@type"`
	URL       string `json:"url"`
	Name      string `json:"name"`
	StartDate string `json:"startDate"`
	EndDate   string `json:"endDate"`
	Image     any    `json:"image"`
	Location  struct {
		Name    string `json:"name"`
		Address struct {
			Street   string `json:"streetAddress"`
			Locality string `json:"addressLocality"`
		} `json:"address"`
	} `json:"location"`
	Offers struct {
		URL       string `json:"url"`
		LowPrice  string `json:"lowPrice"`
		HighPrice string `json:"highPrice"`
		Price     string `json:"price"`
	} `json:"offers"`
}

// Parse достаёт события из JSON-LD на странице. now нужен, чтобы отбросить прошедшие.
func Parse(page []byte, now time.Time, cfg Config) ([]collector.Event, Stats) {
	return parseEvents(page, now, cfg, "")
}

func parseEvents(page []byte, now time.Time, cfg Config, categoryAlias string) ([]collector.Event, Stats) {
	var stats Stats
	var out []collector.Event

	for _, m := range ldJSON.FindAllSubmatch(page, -1) {
		for _, node := range eventNodes(graphNodes(m[1])) {
			raw, err := decode(string(node))
			if err != nil {
				stats.Broken++
				continue
			}
			switch raw.Type {
			case "Event", "MusicEvent", "TheaterEvent", "TheatreEvent", "DanceEvent":
			default:
				continue
			}
			if strings.HasSuffix(raw.Status, "EventCancelled") || strings.HasSuffix(raw.Status, "EventPostponed") {
				continue
			}

			e, err := toEvent(raw, cfg.Categories)
			if err != nil {
				stats.Broken++
				continue
			}
			if e.StartsAt.Before(now) && (e.EndsAt == nil || e.EndsAt.Before(now)) {
				stats.Past++
				continue
			}
			if categoryAlias != "" {
				e.CategoryAlias = categoryAlias
			}
			if e.CategoryAlias == "" {
				stats.NoCategory++
				continue
			}
			out = append(out, e)
		}
	}

	return out, stats
}

func eventNodes(nodes []json.RawMessage) []json.RawMessage {
	var out []json.RawMessage
	for _, node := range nodes {
		out = append(out, node)
		var wrapper struct {
			MainEntity json.RawMessage `json:"mainEntity"`
			Items      []struct {
				Item json.RawMessage `json:"item"`
			} `json:"itemListElement"`
		}
		if json.Unmarshal(node, &wrapper) != nil {
			continue
		}
		if len(wrapper.MainEntity) > 0 {
			out = append(out, eventNodes([]json.RawMessage{wrapper.MainEntity})...)
		}
		for _, item := range wrapper.Items {
			if len(item.Item) > 0 {
				out = append(out, item.Item)
			}
		}
	}
	return out
}

// decode терпит сырые переводы строк в тексте и битые описания: описание нам не нужно.
func decode(s string) (ldEvent, error) {
	var ev ldEvent

	clean := controlChars.ReplaceAllString(s, " ")
	if err := json.Unmarshal([]byte(clean), &ev); err == nil {
		return ev, nil
	}

	stripped := descriptionSpan.ReplaceAllStringFunc(clean, func(m string) string {
		if strings.HasSuffix(strings.TrimSpace(m), ",\"offers\"") || strings.Contains(m, `"offers"`) {
			return `"offers"`
		}
		return "}"
	})
	if err := json.Unmarshal([]byte(stripped), &ev); err != nil {
		return ev, err
	}
	return ev, nil
}

func toEvent(r ldEvent, categories map[string]string) (collector.Event, error) {
	u, err := url.Parse(r.URL)
	if err != nil || r.Name == "" {
		return collector.Event{}, errors.New("нет url или названия")
	}
	path := strings.Trim(u.Path, "/")
	if path == "" {
		return collector.Event{}, errors.New("пустой путь")
	}
	section := strings.SplitN(path, "/", 2)[0]
	category := categories[section]
	if lastSlash := strings.LastIndex(path, "/"); lastSlash >= 0 && categories[path[:lastSlash]] != "" {
		category = categories[path[:lastSlash]]
	}

	starts, err := parseTime(r.StartDate)
	if err != nil {
		return collector.Event{}, err
	}

	var ends *time.Time
	if strings.Contains(r.EndDate, "T") {
		if t, err := parseTime(r.EndDate); err == nil {
			ends = &t
		}
	}

	venue := strings.TrimSpace(r.Location.Name)
	locality := strings.Trim(strings.TrimSpace(r.Location.Address.Locality), ".")

	var hints []string
	if locality != "" {
		hints = append(hints, locality)
	}
	if i := strings.LastIndex(venue, ","); i >= 0 {
		hints = append(hints, strings.TrimSpace(venue[i+1:]))
		venue = strings.TrimSpace(venue[:i])
	}

	e := collector.Event{
		ExternalID:    externalID(path),
		Slug:          path[strings.LastIndex(path, "/")+1:],
		Title:         strings.TrimSpace(r.Name),
		SourceURL:     r.URL,
		TicketURL:     firstNonEmpty(r.Offers.URL, r.URL),
		ImageURL:      firstImage(r.Image),
		CategoryAlias: category,
		CityHints:     hints,
		VenueName:     venue,
		Address:       strings.TrimSpace(r.Location.Address.Street),
		StartsAt:      starts,
		EndsAt:        ends,
	}

	low := cleanPrice(firstNonEmpty(r.Offers.LowPrice, r.Offers.Price))
	if low != "" {
		e.PriceMin = &low
		e.IsFree = low == "0" || low == "0.00"
	}
	if high := cleanPrice(r.Offers.HighPrice); high != "" {
		e.PriceMax = &high
	}

	return e, nil
}

var minsk = func() *time.Location {
	loc, err := time.LoadLocation("Europe/Minsk")
	if err != nil {
		return time.FixedZone("MSK", 3*3600)
	}
	return loc
}()

func parseTime(s string) (time.Time, error) {
	for _, layout := range []string{"2006-01-02T15:04:05-0700", time.RFC3339} {
		if t, err := time.Parse(layout, s); err == nil {
			return t, nil
		}
	}
	if t, err := time.ParseInLocation("2006-01-02T15:04:05", s, minsk); err == nil {
		return t, nil
	}
	return time.ParseInLocation("2006-01-02", s, minsk)
}

func firstNonEmpty(vals ...string) string {
	for _, v := range vals {
		if strings.TrimSpace(v) != "" {
			return strings.TrimSpace(v)
		}
	}
	return ""
}

func firstImage(v any) string {
	switch t := v.(type) {
	case string:
		return t
	case []any:
		if len(t) > 0 {
			if s, ok := t[0].(string); ok {
				return s
			}
		}
	}
	return ""
}

// cleanPrice: "1 445.00" (с пробелом или неразрывным пробелом) -> "1445.00".
func cleanPrice(s string) string {
	return strings.Map(func(r rune) rune {
		if r == ' ' || r == '\u00a0' || r == '\u202f' {
			return -1
		}
		return r
	}, strings.TrimSpace(s))
}

// externalID влезает в VARCHAR(128): длинные пути режем и добавляем хэш, чтобы остались уникальными.
func externalID(path string) string {
	if len(path) <= 128 {
		return path
	}
	sum := sha1.Sum([]byte(path))
	return path[:100] + "-" + hex.EncodeToString(sum[:])[:12]
}
