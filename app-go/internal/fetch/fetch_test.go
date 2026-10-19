package fetch

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync/atomic"
	"testing"
	"time"
)

func newClient() *Client { return New("TestBot/1.0", time.Millisecond) }

func TestGetSendsUserAgent(t *testing.T) {
	var ua string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		ua = r.Header.Get("User-Agent")
		w.Write([]byte("ok"))
	}))
	defer srv.Close()

	body, err := newClient().Get(context.Background(), srv.URL)
	if err != nil || string(body) != "ok" {
		t.Fatalf("body=%q err=%v", body, err)
	}
	if ua != "TestBot/1.0" {
		t.Errorf("User-Agent = %q", ua)
	}
}

func TestGetDoesNotRetryOn403(t *testing.T) {
	var calls atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls.Add(1)
		w.WriteHeader(http.StatusForbidden)
	}))
	defer srv.Close()

	_, err := newClient().Get(context.Background(), srv.URL)
	if err == nil || !strings.Contains(err.Error(), "403") {
		t.Fatalf("ожидали ошибку про 403, получили %v", err)
	}
	if calls.Load() != 1 {
		t.Errorf("при 403 нельзя настаивать: запросов %d", calls.Load())
	}
}

func TestGetDoesNotRetryOn404(t *testing.T) {
	var calls atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls.Add(1)
		w.WriteHeader(http.StatusNotFound)
	}))
	defer srv.Close()

	if _, err := newClient().Get(context.Background(), srv.URL); err == nil {
		t.Fatal("ожидали ошибку")
	}
	if calls.Load() != 1 {
		t.Errorf("запросов %d, ожидали 1", calls.Load())
	}
}

func TestGetStopsOnCancelledContext(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusServiceUnavailable)
	}))
	defer srv.Close()

	ctx, cancel := context.WithTimeout(context.Background(), 100*time.Millisecond)
	defer cancel()

	start := time.Now()
	if _, err := newClient().Get(ctx, srv.URL); err == nil {
		t.Fatal("ожидали ошибку")
	}
	if time.Since(start) > 3*time.Second {
		t.Error("отмена контекста должна прерывать повторы")
	}
}
