package fetch

import (
	"context"
	"errors"
	"fmt"
	"io"
	"net/http"
	"sync"
	"time"

	"github.com/temoto/robotstxt"
)

const maxBody = 16 << 20

// StatusError — сайт ответил кодом, который не лечится повтором.
type StatusError struct {
	URL  string
	Code int
}

func (e *StatusError) Error() string { return fmt.Sprintf("GET %s: status %d", e.URL, e.Code) }

// Client вежливый: честный User-Agent, пауза между запросами, повторы с нарастающей паузой.
type Client struct {
	http      *http.Client
	userAgent string
	interval  time.Duration

	mu   sync.Mutex
	last time.Time
}

func New(userAgent string, interval time.Duration) *Client {
	return &Client{
		http:      &http.Client{Timeout: 30 * time.Second},
		userAgent: userAgent,
		interval:  interval,
	}
}

func (c *Client) wait(ctx context.Context) error {
	c.mu.Lock()
	defer c.mu.Unlock()

	if d := c.interval - time.Since(c.last); d > 0 {
		select {
		case <-time.After(d):
		case <-ctx.Done():
			return ctx.Err()
		}
	}
	c.last = time.Now()
	return nil
}

func (c *Client) Get(ctx context.Context, url string) ([]byte, error) {
	backoff := 2 * time.Second
	var lastErr error

	for attempt := 0; attempt < 3; attempt++ {
		if err := c.wait(ctx); err != nil {
			return nil, err
		}

		req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
		if err != nil {
			return nil, err
		}
		req.Header.Set("User-Agent", c.userAgent)
		req.Header.Set("Accept", "text/html,application/xhtml+xml")

		resp, err := c.http.Do(req)
		if err == nil {
			body, readErr := io.ReadAll(io.LimitReader(resp.Body, maxBody))
			resp.Body.Close()

			switch {
			case readErr != nil:
				err = readErr
			case resp.StatusCode == http.StatusOK:
				return body, nil
			case resp.StatusCode == http.StatusForbidden:
				// 403 — нас не хотят видеть, не настаиваем
				return nil, &StatusError{URL: url, Code: resp.StatusCode}
			case resp.StatusCode == http.StatusTooManyRequests || resp.StatusCode >= 500:
				err = fmt.Errorf("status %d", resp.StatusCode)
			default:
				return nil, &StatusError{URL: url, Code: resp.StatusCode}
			}
		}

		lastErr = err
		select {
		case <-time.After(backoff):
			backoff *= 2
		case <-ctx.Done():
			return nil, ctx.Err()
		}
	}

	return nil, fmt.Errorf("GET %s: %w", url, lastErr)
}

// Robots returns nil when the source has no robots.txt. Other errors stop collection.
func (c *Client) Robots(ctx context.Context, baseURL, userAgent string) (*robotstxt.Group, error) {
	body, err := c.Get(ctx, baseURL+"/robots.txt")
	if err != nil {
		var status *StatusError
		if errors.As(err, &status) && (status.Code == http.StatusNotFound || status.Code == http.StatusGone) {
			return nil, nil
		}
		return nil, fmt.Errorf("robots.txt не прочитался: %w", err)
	}
	rules, err := robotstxt.FromBytes(body)
	if err != nil {
		return nil, fmt.Errorf("robots.txt не разобрался: %w", err)
	}
	return rules.FindGroup(userAgent), nil
}
