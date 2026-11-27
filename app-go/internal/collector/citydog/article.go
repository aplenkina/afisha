package citydog

import (
	"crypto/sha256"
	"encoding/json"
	"fmt"
	"net/url"
	"regexp"
	"strconv"
	"strings"
	"time"
	"unicode/utf8"

	"golang.org/x/net/html"

	"afisha/internal/collector"
)

var (
	dateLine      = regexp.MustCompile(`^([0-9]{1,2}) ([\p{L}]+),? ([0-9]{1,2}):([0-9]{2})\.?$`)
	venueLine     = regexp.MustCompile(`^(.+?) \(([^()]+)\)\.?$`)
	priceLine     = regexp.MustCompile(`(?i)^([0-9]+(?:[.,][0-9]{1,2})?)(?:\s*[–—-]\s*([0-9]+(?:[.,][0-9]{1,2})?))?\s*(?:руб\.?|BYN)(?:[\s.,;]|$)`)
	instagramPath = regexp.MustCompile(`^/(?:p|reel)/([A-Za-z0-9_-]+)/?$`)
	quotedTitle   = regexp.MustCompile(`«([^»]+)»`)
)

var months = map[string]time.Month{
	"января": 1, "студзеня": 1, "февраля": 2, "лютага": 2, "марта": 3, "сакавіка": 3,
	"апреля": 4, "красавіка": 4, "мая": 5, "траўня": 5, "июня": 6, "чэрвеня": 6,
	"июля": 7, "ліпеня": 7, "августа": 8, "жніўня": 8, "сентября": 9, "верасня": 9,
	"октября": 10, "кастрычніка": 10, "ноября": 11, "лістапада": 11, "декабря": 12, "снежня": 12,
}

type eventBlock struct {
	heading, instagram string
	facts              *html.Node
	paragraph          string
	names              []string
}

func parseArticle(page []byte, address, city string, now time.Time) ([]collector.Event, int, int) {
	doc, err := html.Parse(strings.NewReader(string(page)))
	if err != nil {
		return nil, 0, 1
	}
	var published time.Time
	var article *html.Node
	for n := range doc.Descendants() {
		if n.Data == "script" && attr(n, "type") == "application/ld+json" && n.FirstChild != nil {
			var meta struct {
				Type      string    `json:"@type"`
				Published time.Time `json:"datePublished"`
			}
			if json.Unmarshal([]byte(n.FirstChild.Data), &meta) == nil && meta.Type == "NewsArticle" {
				published = meta.Published
			}
		}
		if n.Data == "article" && strings.Contains(" "+attr(n, "class")+" ", " postPage-contentText ") {
			article = n
			break
		}
	}
	if article == nil || published.IsZero() {
		return nil, 0, 1
	}

	var blocks []eventBlock
	for n := range article.Descendants() {
		if n.Data == "h2" {
			blocks = append(blocks, eventBlock{heading: nodeText(n)})
		}
		if len(blocks) == 0 {
			continue
		}
		b := &blocks[len(blocks)-1]
		switch n.Data {
		case "blockquote":
			if u := httpsLink(attr(n, "data-instgrm-permalink")); u != nil &&
				(u.Host == "www.instagram.com" || u.Host == "instagram.com") && instagramPath.MatchString(u.Path) {
				u.RawQuery, u.Fragment = "", ""
				b.instagram = u.String()
			}
		case "ul":
			first := firstListItem(n)
			if b.facts == nil && first != nil && dateLine.MatchString(strings.ToLower(nodeText(first))) {
				b.facts = n
			}
		case "p":
			if b.facts != nil && b.paragraph == "" {
				b.paragraph = nodeText(n)
				for child := range n.Descendants() {
					if child.Data == "strong" {
						b.names = append(b.names, nodeText(child))
					}
				}
			}
		}
	}

	var events []collector.Event
	past, broken := 0, 0
	for _, b := range blocks {
		if b.facts == nil {
			if b.instagram != "" {
				broken++
			}
			continue
		}
		e, ok := b.event(address, city, published.In(now.Location()), now)
		if !ok {
			broken++
		} else if !e.StartsAt.After(now) {
			past++
		} else {
			events = append(events, e)
		}
	}
	if len(events)+past+broken == 0 {
		broken++
	}
	return events, past, broken
}

func (b eventBlock) event(address, city string, published, now time.Time) (collector.Event, bool) {
	var lines []*html.Node
	for n := range b.facts.ChildNodes() {
		if n.Data == "li" {
			lines = append(lines, n)
		}
	}
	if len(lines) < 3 || b.heading == "" {
		return collector.Event{}, false
	}
	date := nodeText(lines[0])
	start := eventDate(strings.ToLower(date), published)
	place := venueLine.FindStringSubmatch(nodeText(lines[1]))
	if start.IsZero() || place == nil {
		return collector.Event{}, false
	}

	title, category := b.titleAndCategory()
	if utf8.RuneCountInString(title) > 255 || utf8.RuneCountInString(place[1]) > 255 || utf8.RuneCountInString(place[2]) > 255 {
		return collector.Event{}, false
	}
	identity := b.instagram
	if identity == "" {
		identity = address + "#" + strings.ToLower(b.heading)
	}
	hash := sha256.Sum256([]byte(identity))
	id := fmt.Sprintf("%x", hash[:16])
	price := nodeText(lines[2])
	min, max, free := parsePrice(price)
	e := collector.Event{
		ExternalID: id, Slug: "citydog-" + id, Title: title, SourceURL: address,
		CityHints: []string{city}, CategoryAlias: category, VenueName: place[1], Address: place[2],
		StartsAt: start, PriceMin: min, PriceMax: max, IsFree: free,
		Observation: &collector.Observation{
			SourceName: "CityDog", OriginalURL: b.instagram, URL: address, CheckedAt: now,
			Status: collector.ObservedScheduled, StartsAt: start.Format(time.RFC3339), Title: title,
			DisplayedTitle: b.heading, DisplayedDate: date, DisplayedPrice: price, Venue: place[1], Address: place[2],
			PriceMin: min, PriceMax: max, Issues: []string{"Сверьте анонс с организатором перед публикацией."},
		},
	}
	for n := range lines[2].Descendants() {
		if n.Data != "a" {
			continue
		}
		if u := httpsLink(attr(n, "href")); u != nil {
			switch u.Hostname() {
			case "bezkassira.by", "ticketpro.by", "hubl.by":
				u.RawQuery, u.Fragment = "", ""
				e.TicketURL = u.String()
			}
		}
	}
	return e, true
}

func (b eventBlock) titleAndCategory() (string, string) {
	context := strings.ToLower(b.heading + " " + b.paragraph)
	switch {
	case hasAny(context, "кино", "кіно", "фильм", "стужк"):
		// A quoted venue can precede the film in the opening sentence.
		for _, match := range quotedTitle.FindAllStringSubmatch(b.paragraph, -1) {
			before, _, _ := strings.Cut(b.paragraph, match[0])
			if hasAny(strings.ToLower(before), "фильм", "стужк") {
				return "Кинопоказ «" + match[1] + "»", "cinema"
			}
		}
		return b.heading, "cinema"
	case hasAny(context, "вечерин", "вечарын", "дидже", "дыджэ", "диджэ", "dj-сет"):
		return b.heading, "party"
	case hasAny(context, "концерт", "гурт", "групп", "хардкор", "музыкант", "музыкі"):
		if len(b.names) == 1 && b.names[0] != "" {
			return "Концерт " + b.names[0], "concert"
		}
		return b.heading, "concert"
	case hasAny(context, "выставк", "выстав", "выстаў"):
		return b.heading, "exhibition"
	case hasAny(context, "мастер", "майстар", "лекци", "лекцы", "коллаж", "калаж"):
		return b.heading, "workshop"
	default:
		return b.heading, "culture"
	}
}

func eventDate(value string, published time.Time) time.Time {
	m := dateLine.FindStringSubmatch(value)
	if m == nil || months[m[2]] == 0 {
		return time.Time{}
	}
	day, _ := strconv.Atoi(m[1])
	hour, _ := strconv.Atoi(m[3])
	min, _ := strconv.Atoi(m[4])
	if hour > 23 || min > 59 {
		return time.Time{}
	}
	for _, year := range []int{published.Year(), published.Year() + 1} {
		t := time.Date(year, months[m[2]], day, hour, min, 0, 0, published.Location())
		if t.Day() == day && !t.Before(published.Add(-24*time.Hour)) && !t.After(published.AddDate(0, 0, 14)) {
			return t
		}
	}
	return time.Time{}
}

func parsePrice(value string) (min, max *string, free bool) {
	label, amount, ok := strings.Cut(strings.ToLower(value), ":")
	if !ok || (strings.TrimSpace(label) != "вход" && strings.TrimSpace(label) != "уваход") {
		return nil, nil, false
	}
	amount = strings.TrimSpace(amount)
	head, _, _ := strings.Cut(amount, " ")
	head = strings.Trim(head, ".;,")
	if head == "свободный" || head == "бесплатно" || head == "вольны" || head == "бясплатна" {
		return nil, nil, true
	}
	if m := priceLine.FindStringSubmatch(amount); m != nil {
		low := strings.ReplaceAll(m[1], ",", ".")
		if m[2] != "" {
			high := strings.ReplaceAll(m[2], ",", ".")
			return &low, &high, false
		}
		return &low, nil, false
	}
	return nil, nil, false
}

func httpsLink(value string) *url.URL {
	u, err := url.Parse(value)
	if err == nil && u.Scheme == "https" && u.Host != "" && u.User == nil {
		return u
	}
	return nil
}

func hasAny(value string, fragments ...string) bool {
	for _, fragment := range fragments {
		if strings.Contains(value, fragment) {
			return true
		}
	}
	return false
}

func firstListItem(n *html.Node) *html.Node {
	for child := range n.ChildNodes() {
		if child.Data == "li" {
			return child
		}
	}
	return nil
}

func attr(n *html.Node, name string) string {
	for _, attr := range n.Attr {
		if attr.Key == name {
			return attr.Val
		}
	}
	return ""
}

func nodeText(n *html.Node) string {
	var b strings.Builder
	for child := range n.Descendants() {
		if child.Type == html.TextNode {
			b.WriteString(child.Data)
		}
	}
	return strings.Join(strings.Fields(b.String()), " ")
}
