package notify

import (
	"context"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"net/url"
	"strings"
	"time"
)

type Notifier interface {
	Send(ctx context.Context, text string)
}

type Telegram struct {
	token  string
	chatID string
	log    *slog.Logger

	APIBase string
	client  *http.Client
}

func NewTelegram(token, chatID string, log *slog.Logger) *Telegram {
	return &Telegram{
		token: token, chatID: chatID, log: log,
		APIBase: "https://api.telegram.org",
		client:  &http.Client{Timeout: 15 * time.Second},
	}
}

func (t *Telegram) Configured() bool { return t.token != "" && t.chatID != "" }

// Ошибка отправки не должна ронять сбор, поэтому только пишется в лог.
func (t *Telegram) Send(ctx context.Context, text string) {
	t.log.Warn("уведомление", "text", text)
	if !t.Configured() {
		t.log.Warn("telegram не настроен: задайте TELEGRAM_BOT_TOKEN(_FILE) и TELEGRAM_CHAT_ID(_FILE), сообщение не отправлено")
		return
	}
	if err := t.send(ctx, text); err != nil {
		t.log.Error("telegram: сообщение не отправлено", "err", err)
	}
}

func (t *Telegram) Test(ctx context.Context) error {
	if !t.Configured() {
		return errors.New("telegram не настроен: нет токена или id чата")
	}
	return t.send(ctx, "Проверка связи: сборщик Афиши РБ умеет писать в этот чат.")
}

func (t *Telegram) send(ctx context.Context, text string) error {
	ctx, cancel := context.WithTimeout(ctx, 15*time.Second)
	defer cancel()

	form := url.Values{"chat_id": {t.chatID}, "text": {text}}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost,
		fmt.Sprintf("%s/bot%s/sendMessage", t.APIBase, t.token), strings.NewReader(form.Encode()))
	if err != nil {
		return t.redact(err)
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")

	resp, err := t.client.Do(req)
	if err != nil {
		return t.redact(err)
	}
	defer resp.Body.Close()

	if resp.StatusCode == http.StatusOK {
		return nil
	}

	body, _ := io.ReadAll(io.LimitReader(resp.Body, 512))
	return fmt.Errorf("telegram ответил %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
}

// net/http кладёт в ошибку полный URL, а в нём токен.
func (t *Telegram) redact(err error) error {
	return errors.New(strings.ReplaceAll(err.Error(), t.token, "***"))
}
