package eventtitle

import (
	"regexp"
	"strings"
	"unicode"
)

var (
	quotedName     = regexp.MustCompile(`«[^«»]+»|"[^"]+"|“[^“”]+”|„[^„“”]+[“”]|‹[^‹›]+›|‘[^‘’]+’`)
	quoteMark      = regexp.MustCompile(`[«»"“”„‹›‘]`)
	programme      = regexp.MustCompile(`(?i)(?:\b|\s|^)с\s+программой\s*[:–—-]?\s*$`)
	project        = regexp.MustCompile(`(?i)^\s*проект\s*$`)
	performer      = regexp.MustCompile(`(?i)^в\s+исполнении(?:\s+\pL+)*$`)
	violinConcerto = regexp.MustCompile(`(?i)(?:^|[^\pL\pN])концерт[^\pL\pN]*для[^\pL\pN]*скрипки(?:$|[^\pL\pN])`)
	typeLabel      = regexp.MustCompile(`(?i)^\s*(?:(?:детский|детская|сольный|юбилейный|праздничный|джазовый|музыкальная)\s+)?(?:концерт|спектакль|cпектакль|кинопоказ|фильм|балет|опера|мюзикл(?:-биография)?|комедия|выставка|экскурсия|лекция|мастер-класс|шоу(?:-дискотека)?|музыкальная\s+(?:программа|сказка)|концертная\s+программа|программа)(?:\s*[:–—]\s*|\s+-\s*|\s+|$)`)
	withoutQuotes  = map[string]*regexp.Regexp{
		"ticketpro":  regexp.MustCompile(`(?i)^(?:спектакль|cпектакль)\s+`),
		"citydog":    regexp.MustCompile(`(?i)^(?:концерт|кинопоказ)\s+`),
		"bezkassira": regexp.MustCompile(`(?i)^концерт\s+группы\s+`),
	}
)

type Title struct {
	Display      string
	ReviewReason string
}

func Extract(name, source string) Title {
	name = strings.TrimSpace(name)
	if violinConcerto.MatchString(name) {
		return Title{format(name), "Слово «концерт» может быть частью названия произведения."}
	}

	name, reason := extractName(name, source)
	if strings.IndexFunc(name, func(r rune) bool { return unicode.IsLetter(r) || unicode.IsNumber(r) }) == -1 {
		return Title{ReviewReason: "В заголовке источника нет названия."}
	}
	return Title{format(name), reason}
}

func extractName(name, source string) (string, string) {
	quotes := quotedName.FindAllStringIndex(name, -1)
	if quoteMark.MatchString(quotedName.ReplaceAllString(name, "")) {
		return name, "В заголовке источника есть незакрытые или вложенные кавычки."
	}

	var programNames []string
	for i, match := range quotes {
		start := 0
		if i > 0 {
			start = quotes[i-1][1]
		}
		if programme.MatchString(name[start:match[0]]) {
			programNames = append(programNames, insideQuotes(name, match))
		}
	}
	if len(programNames) == 1 {
		return programNames[0], ""
	}

	switch len(quotes) {
	case 0:
		if label := withoutQuotes[source]; label != nil && label.MatchString(name) {
			return label.ReplaceAllString(name, ""), ""
		}
		if label := typeLabel.FindString(name); strings.HasSuffix(strings.TrimSpace(label), "-") || strings.ContainsAny(label, ":–—") {
			return strings.TrimPrefix(name, label), ""
		}
		if typeLabel.MatchString(name) {
			return name, "Нужно отделить название от типа или описания события."
		}
	case 1:
		match := quotes[0]
		prefix := strings.TrimSpace(name[:match[0]])
		suffix := strings.TrimSpace(name[match[1]:])
		if suffix == "" && (prefix == "" || typeLabel.MatchString(prefix)) {
			return insideQuotes(name, match), ""
		}
	case 2:
		separator := strings.TrimSpace(name[quotes[0][1]:quotes[1][0]])
		if strings.TrimSpace(name[quotes[1][1]:]) == "" {
			prefix := name[:quotes[0][0]]
			if project.MatchString(prefix) && (separator == "—" || separator == "–" || separator == "-") {
				return insideQuotes(name, quotes[1]), ""
			}
			if typeLabel.MatchString(prefix) && performer.MatchString(separator) {
				return insideQuotes(name, quotes[0]), ""
			}
		}
	}
	if len(quotes) > 0 {
		return name, "Нужно проверить, какой текст в кавычках является названием."
	}
	return name, ""
}

func insideQuotes(name string, match []int) string {
	return strings.TrimSpace(strings.Trim(name[match[0]:match[1]], `«»"“”„‹›‘’`))
}

func format(name string) string {
	var display strings.Builder
	firstLetter := true
	betweenWords := false
	var previous rune
	for _, r := range strings.TrimSpace(name) {
		word := unicode.IsLetter(r) || unicode.IsNumber(r) || unicode.IsMark(r)
		if !word && !unicode.IsSpace(r) && r != ':' && r != ',' {
			betweenWords = betweenWords || unicode.IsLetter(previous) || unicode.IsNumber(previous)
			continue
		}
		if betweenWords && word {
			display.WriteByte(' ')
		}
		betweenWords = false
		if unicode.IsLetter(r) {
			if firstLetter {
				r = unicode.ToUpper(r)
				firstLetter = false
			} else {
				r = unicode.ToLower(r)
			}
		}
		display.WriteRune(r)
		previous = r
	}
	return strings.TrimSpace(display.String())
}
