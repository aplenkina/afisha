package collector

import (
	"context"
	"time"
)

type Event struct {
	ExternalID    string // не длиннее 128 символов
	Slug          string
	Title         string
	SourceURL     string
	TicketURL     string
	ImageURL      string
	CategoryAlias string
	CityHints     []string // кандидаты на название города, по порядку надёжности
	VenueName     string
	Address       string
	StartsAt      time.Time
	EndsAt        *time.Time
	PriceMin      *string
	PriceMax      *string
	IsFree        bool
}

type Stats struct {
	PagesTotal   int
	PagesLoaded  int
	PagesBlocked int // закрыты в robots.txt
	PagesFailed  int
	Past         int
	NoCategory   int // события из разделов без категории в настройках
	Broken       int // блоки разметки, которые не разобрались
}

type Result struct {
	Events []Event
	Stats  Stats
}

type Collector interface {
	Slug() string
	Name() string
	BaseURL() string
	Collect(ctx context.Context) (Result, error)
}
