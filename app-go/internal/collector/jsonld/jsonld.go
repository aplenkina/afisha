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

type Config struct {
	// Pages — пути страниц со списком событий относительно адреса источника, например "/", "/concerts/".
	Pages []string `json:"pages"`
	// Categories — первый сегмент пути события -> alias категории. Остальные разделы пропускаются.
	Categories   map[string]string `json:"categories"`
	CityFallback bool              `json:"city_fallback"`
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

func New(slug, name, baseURL, cityName string, cfg Config, client *fetch.Client, userAgent string, log *slog.Logger) *Collector {
	return &Collector{
		slug: slug, name: name, baseURL: strings.TrimRight(baseURL, "/"),
		cityName: cityName, cfg: cfg, client: client, userAgent: userAgent, log: log,
	}
}

func (c *Collector) Slug() string    { return c.slug }
func (c *Collector) Name() string    { return c.name }
func (c *Collector) BaseURL() string { return c.baseURL }

// Нет robots.txt — можно всё; любая другая ошибка — не собираем вслепую.
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

		events, stats := Parse(body, time.Now(), c.cfg)
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

type ldEvent struct {
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

func Parse(page []byte, now time.Time, cfg Config) ([]collector.Event, Stats) {
	var stats Stats
	var out []collector.Event

	for _, m := range ldJSON.FindAllSubmatch(page, -1) {
		raw, err := decode(string(m[1]))
		if err != nil {
			stats.Broken++
			continue
		}
		if raw.Type != "Event" {
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
		if e.CategoryAlias == "" {
			stats.NoCategory++
			continue
		}
		out = append(out, e)
	}

	return out, stats
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
		CategoryAlias: categories[section],
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
