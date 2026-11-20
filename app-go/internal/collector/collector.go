package collector

import (
	"context"
	"time"
)

// Event — факты о событии, которые забирает сборщик. Тексты и фото целиком не копируем.
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

// Stats — что сборщик увидел по дороге; попадает в итоговую сводку.
type Stats struct {
	PagesTotal   int // страниц в настройках
	PagesLoaded  int
	PagesBlocked int // закрыты в robots.txt
	PagesFailed  int // не загрузились
	Past         int // прошедшие события
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
