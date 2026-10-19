package jsonld

import (
	"strings"
	"testing"
)

func TestCleanPrice(t *testing.T) {
	cases := map[string]string{
		"1 445.00": "1445.00",
		"1 445.00": "1445.00",
		"1 445.00": "1445.00",
		" 80.00 ":  "80.00",
		"":         "",
	}
	for in, want := range cases {
		if got := cleanPrice(in); got != want {
			t.Errorf("cleanPrice(%q) = %q, want %q", in, got, want)
		}
	}
}

func TestExternalID(t *testing.T) {
	short := "bilety-na-koncert/tatu-11-noyabrya-2026"
	if got := externalID(short); got != short {
		t.Errorf("короткий путь не должен меняться: %q", got)
	}

	a := "bilety-na-koncert/" + strings.Repeat("a", 200) + "-1"
	b := "bilety-na-koncert/" + strings.Repeat("a", 200) + "-2"
	ga, gb := externalID(a), externalID(b)

	if len(ga) > 128 || len(gb) > 128 {
		t.Errorf("не влезает в VARCHAR(128): %d, %d", len(ga), len(gb))
	}
	if ga == gb {
		t.Error("пути с общим началом должны давать разные id")
	}
	if externalID(a) != ga {
		t.Error("id должен быть стабильным между запусками")
	}
}

func TestDecodeSurvivesBrokenDescription(t *testing.T) {
	raw := "{\n\"@type\": \"Event\",\n\"name\": \"Тест\",\n\"description\": \"текст с \\q плохим\nэскейпом и \"кавычками\"\",\n\"offers\": {\"lowPrice\": \"5.00\"}\n}"

	ev, err := decode(raw)
	if err != nil {
		t.Fatalf("decode: %v", err)
	}
	if ev.Name != "Тест" || ev.Offers.LowPrice != "5.00" {
		t.Errorf("поля вокруг описания потеряны: %+v", ev)
	}
}
