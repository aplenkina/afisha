package eventtitle

import "testing"

func TestExtract(t *testing.T) {
	tests := []struct {
		name   string
		source string
		input  string
		want   string
		review bool
	}{
		{"programme", "ticketpro", `Джазовый концерт Уэйна Эскоффери с программой "САКСОФОН-КОРОЛЬ ДЖАЗА"`, "Саксофон король джаза", false},
		{"artist and programme", "hubl", `Квинтет "Репутация" с программой "Музыка при свете 1000 свечей"`, "Музыка при свете 1000 свечей", false},
		{"programme followed by artist", "ticketpro", "Концертная программа «Как упоительны в России вечера» в исполнении группы «Белый Орел»", "Как упоительны в россии вечера", false},
		{"film project", "bezkassira", "Проект «Киномеханика» — «Шепоты и крики»", "Шепоты и крики", false},
		{"single quote pair", "citydog", "Кинопоказ «Любовное настроение»", "Любовное настроение", false},
		{"no quotes", "ticketpro", "Спектакль Каникулы", "Каникулы", false},
		{"explicit separator", "hubl", "Спектакль : ВИШНЁВЫЙ САД", "Вишнёвый сад", false},
		{"title carries subtitle", "bezkassira", "ЧЁРНАЯ ПАНТЕРА: ВАКАНДА НАВЕКИ", "Чёрная пантера: ваканда навеки", false},
		{"spacing kept", "bezkassira", "Комедия «ОСТОРОЖНО,  КЕКС»", "Осторожно,  кекс", false},
		{"ambiguous quote pairs", "ticketpro", `Сольный концерт шоу "Аллюр" "Пой, гитара звонкая!"`, "Сольный концерт шоу аллюр пой, гитара звонкая", true},
		{"truncated title", "bezkassira", "Программа «Музыкальные баллады: Бах", "Программа музыкальные баллады: бах", true},
		{"concerto review", "citydog", "КОНЦЕРТ — ДЛЯ СКРИПКИ и оркестра", "Концерт  для скрипки и оркестра", true},
		{"generic description", "bezkassira", "Концерт ансамбля народной музыки", "Концерт ансамбля народной музыки", true},
	}
	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			got := Extract(test.input, test.source)
			if got.Display != test.want || (got.ReviewReason != "") != test.review {
				t.Fatalf("Extract(%q, %q) = %#v, want display %q, review %t", test.input, test.source, got, test.want, test.review)
			}
		})
	}
}
