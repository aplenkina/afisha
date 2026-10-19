package notify

import (
	"bytes"
	"context"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"net/url"
	"strings"
	"testing"
)

const secretToken = "123456:SECRET-TOKEN"

func newTelegram(t *testing.T, handler http.HandlerFunc) (*Telegram, *bytes.Buffer) {
	t.Helper()
	srv := httptest.NewServer(handler)
	t.Cleanup(srv.Close)

	logs := &bytes.Buffer{}
	tg := NewTelegram(secretToken, "42", slog.New(slog.NewTextHandler(logs, nil)))
	tg.APIBase = srv.URL
	return tg, logs
}

func TestSendPostsMessageToChat(t *testing.T) {
	var path string
	var form url.Values
	tg, logs := newTelegram(t, func(w http.ResponseWriter, r *http.Request) {
		path = r.URL.Path
		r.ParseForm()
		form = r.PostForm
		w.Write([]byte(`{"ok":true}`))
	})

	tg.Send(context.Background(), "привет")

	if path != "/bot"+secretToken+"/sendMessage" {
		t.Errorf("path = %q", path)
	}
	if form.Get("chat_id") != "42" || form.Get("text") != "привет" {
		t.Errorf("form = %v", form)
	}
	if strings.Contains(logs.String(), "level=ERROR") {
		t.Errorf("успешная отправка не должна писать ошибок: %s", logs)
	}
}

func TestSendLogsTelegramErrorInsteadOfStayingSilent(t *testing.T) {
	tg, logs := newTelegram(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusBadRequest)
		io.WriteString(w, `{"ok":false,"description":"Bad Request: chat not found"}`)
	})

	tg.Send(context.Background(), "x")

	out := logs.String()
	if !strings.Contains(out, "level=ERROR") || !strings.Contains(out, "chat not found") || !strings.Contains(out, "400") {
		t.Errorf("ошибка Telegram должна попасть в лог с причиной: %s", out)
	}
}

func TestErrorsNeverLeakToken(t *testing.T) {
	// Сервер недоступен: net/http вернёт ошибку с полным URL, в котором токен.
	tg, logs := newTelegram(t, func(http.ResponseWriter, *http.Request) {})
	tg.APIBase = "http://127.0.0.1:1"

	tg.Send(context.Background(), "x")
	err := tg.Test(context.Background())

	if strings.Contains(logs.String(), "SECRET-TOKEN") {
		t.Errorf("токен утёк в лог: %s", logs)
	}
	if err == nil || strings.Contains(err.Error(), "SECRET-TOKEN") {
		t.Errorf("Test должен вернуть ошибку без токена: %v", err)
	}
}

func TestNotConfiguredSaysSoInLogAndDoesNotCallAPI(t *testing.T) {
	called := false
	srv := httptest.NewServer(http.HandlerFunc(func(http.ResponseWriter, *http.Request) { called = true }))
	defer srv.Close()

	logs := &bytes.Buffer{}
	tg := NewTelegram("", "", slog.New(slog.NewTextHandler(logs, nil)))
	tg.APIBase = srv.URL

	tg.Send(context.Background(), "x")

	if called {
		t.Error("без токена обращаться к API нельзя")
	}
	if !strings.Contains(logs.String(), "telegram не настроен") {
		t.Errorf("нужно явно сказать в логе, что telegram не настроен: %s", logs)
	}
	if tg.Test(context.Background()) == nil {
		t.Error("Test без настроек должен вернуть ошибку")
	}
}

func TestTestReturnsErrorOnRejectedMessage(t *testing.T) {
	tg, _ := newTelegram(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusUnauthorized)
		io.WriteString(w, `{"ok":false,"description":"Unauthorized"}`)
	})

	err := tg.Test(context.Background())
	if err == nil || !strings.Contains(err.Error(), "Unauthorized") {
		t.Errorf("err = %v", err)
	}
}
