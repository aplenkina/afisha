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
	Observation   *Observation
}

// Observation records facts from a source with one stable card per session.
type Observation struct {
	SourceName             string    `json:"sourceName,omitempty"`
	OriginalURL            string    `json:"originalUrl,omitempty"`
	CheckedAt              time.Time `json:"checkedAt"`
	URL                    string    `json:"url"`
	Status                 string    `json:"status"`
	SourceStatus           string    `json:"sourceStatus,omitempty"`
	Availability           string    `json:"availability,omitempty"`
	StartsAt               string    `json:"startsAt,omitempty"`
	EndsAt                 string    `json:"endsAt,omitempty"`
	DisplayedDate          string    `json:"displayedDate,omitempty"`
	DisplayedPrice         string    `json:"displayedPrice,omitempty"`
	Venue                  string    `json:"venue,omitempty"`
	Address                string    `json:"address,omitempty"`
	PriceMin               *string   `json:"priceMin,omitempty"`
	PriceMax               *string   `json:"priceMax,omitempty"`
	CheckError             string    `json:"checkError,omitempty"`
	Notice                 string    `json:"notice,omitempty"`
	Issues                 []string  `json:"issues,omitempty"`
	Title                  string    `json:"title,omitempty"`
	DisplayedTitle         string    `json:"displayedTitle,omitempty"`
	DisplayedAddress       string    `json:"displayedAddress,omitempty"`
	DraftsOnly             bool      `json:"-"`
	VerifiedForPublication bool      `json:"verifiedForPublication,omitempty"`
}

const (
	ObservedScheduled   = "scheduled"
	ObservedSoldOut     = "sold_out"
	ObservedCancelled   = "cancelled"
	ObservedPostponed   = "postponed"
	ObservedReview      = "review"
	ObservedUnavailable = "unavailable"
)

func (o Observation) HasConfirmedSession() bool {
	return o.Status == ObservedScheduled || o.Status == ObservedSoldOut
}

// Stats — что сборщик увидел по дороге; попадает в итоговую сводку.
type Stats struct {
	PagesTotal    int // страниц в настройках
	PagesLoaded   int
	PagesBlocked  int // закрыты в robots.txt
	PagesFailed   int
	DetailsFailed int // не загрузились
	Past          int // прошедшие события
	NoCategory    int // события из разделов без категории в настройках
	Broken        int // блоки разметки, которые не разобрались
}

type Result struct {
	Events     []Event
	Stats      Stats
	AllowEmpty bool // прочитанная выборка содержит только прошедшие события
}

type Collector interface {
	Slug() string
	Name() string
	BaseURL() string
	Collect(ctx context.Context) (Result, error)
}
