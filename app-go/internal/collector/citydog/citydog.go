package citydog

import (
	"context"
	"fmt"
	"log/slog"
	"net/url"
	"regexp"
	"strings"
	"time"

	"golang.org/x/net/html"

	"afisha/internal/collector"
	"afisha/internal/fetch"
)

const rubric = "/allposts/rubric/togo/"

var articlePath = regexp.MustCompile(`^/post/[a-zA-Z0-9_-]+/$`)

type Collector struct {
	name, city, baseURL, userAgent string
	client                         *fetch.Client
	loc                            *time.Location
	log                            *slog.Logger
}

func New(name, city, baseURL, userAgent string, client *fetch.Client, loc *time.Location, log *slog.Logger) (*Collector, error) {
	base, err := url.Parse(baseURL)
	if err != nil || base.Scheme != "https" || base.Host != "citydog.io" ||
		(base.Path != "" && base.Path != "/") || base.RawQuery != "" || base.Fragment != "" || base.User != nil {
		return nil, fmt.Errorf("укажите адрес источника https://citydog.io")
	}
	return &Collector{name: name, city: city, baseURL: strings.TrimRight(baseURL, "/"), userAgent: userAgent, client: client, loc: loc, log: log}, nil
}

func (c *Collector) Slug() string    { return "citydog" }
func (c *Collector) Name() string    { return c.name }
func (c *Collector) BaseURL() string { return c.baseURL }

func (c *Collector) Collect(ctx context.Context) (collector.Result, error) {
	var result collector.Result
	rules, err := c.client.Robots(ctx, c.baseURL, c.userAgent)
	if err != nil {
		return result, err
	}
	if rules != nil && !rules.Test(rubric) {
		result.Stats.PagesBlocked++
		return result, fmt.Errorf("рубрика закрыта в robots.txt")
	}
	body, err := c.client.Get(ctx, c.baseURL+rubric)
	if err != nil {
		return result, err
	}
	paths := roundupPaths(body)
	if len(paths) == 0 {
		return result, fmt.Errorf("в рубрике CityDog не найдены подборки на выходные в Минске")
	}

	result.Stats.PagesTotal = len(paths)
	now := time.Now().In(c.loc)
	seen := make(map[string]bool)
	for _, path := range paths {
		if rules != nil && !rules.Test(path) {
			result.Stats.PagesBlocked++
			continue
		}
		address := c.baseURL + path
		page, err := c.client.Get(ctx, address)
		if err != nil {
			result.Stats.PagesFailed++
			c.log.Warn("подборка CityDog не загрузилась", "url", address, "err", err)
			continue
		}
		result.Stats.PagesLoaded++
		events, past, broken := parseArticle(page, address, c.city, now)
		result.Stats.Past += past
		result.Stats.Broken += broken
		for _, e := range events {
			if !seen[e.ExternalID] {
				result.Events = append(result.Events, e)
				seen[e.ExternalID] = true
			}
		}
	}

	result.AllowEmpty = result.Stats.Past > 0 && result.Stats.Broken == 0 &&
		result.Stats.PagesFailed == 0 && result.Stats.PagesBlocked == 0
	return result, nil
}

func roundupPaths(page []byte) []string {
	doc, err := html.Parse(strings.NewReader(string(page)))
	if err != nil {
		return nil
	}
	var paths []string
	seen := make(map[string]bool)
	for n := range doc.Descendants() {
		if n.Data != "a" {
			continue
		}
		path := attr(n, "href")
		if !articlePath.MatchString(path) || seen[path] {
			continue
		}
		title := strings.ToLower(nodeText(n))
		isWeekend := strings.Contains(title, "в выходные в минске") || strings.Contains(title, "ў выхадныя ў мінску")
		if isWeekend {
			paths = append(paths, path)
			seen[path] = true
			if len(paths) == 3 {
				break
			}
		}
	}
	return paths
}
