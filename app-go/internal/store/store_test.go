package store

import "testing"

func TestNormalizeName(t *testing.T) {
	cases := map[string]string{
		"Минск":    "минск",
		"  Минск ": "минск",
		"Могилёв":  "могилев",
		"МОГИЛЁВ":  "могилев",
		"Гродно":   "гродно",
	}
	for in, want := range cases {
		if got := NormalizeName(in); got != want {
			t.Errorf("NormalizeName(%q) = %q, want %q", in, got, want)
		}
	}
}

func TestNullable(t *testing.T) {
	if nullable("") != nil {
		t.Error("пустая строка должна стать NULL")
	}
	if v := nullable("x"); v == nil || *v != "x" {
		t.Errorf("nullable(x) = %v", v)
	}
}
