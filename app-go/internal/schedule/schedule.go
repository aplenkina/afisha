// Package schedule запускает задачу раз в сутки в заданное время по настенным часам.
//
// Таймер time.After здесь не годится: он идёт по монотонным часам, которые стоят, пока Mac спит
// (и вместе с ним виртуалка Docker), поэтому запуск «в 04:00» мог сработать днём. Вместо этого
// раз в несколько секунд сравниваем реальное время с целевым.
package schedule

import (
	"context"
	"fmt"
	"time"
)

type Schedule struct {
	Hour, Minute int
	Loc          *time.Location
	Now          func() time.Time
	// LastRun — когда задача запускалась в последний раз по данным базы. Благодаря ей перезапуск
	// контейнера не вызывает лишний прогон, а пропущенный запуск (контейнер был выключен) догоняется.
	LastRun func(ctx context.Context) (time.Time, error)
	Run     func(ctx context.Context)

	ranAt time.Time // запуск в этом процессе: страхует, если в базе пока нечего записывать
}

func (s *Schedule) now() time.Time {
	if s.Now != nil {
		return s.Now().In(s.Loc)
	}
	return time.Now().In(s.Loc)
}

// Target — сегодняшний момент запуска.
func (s *Schedule) Target(now time.Time) time.Time {
	now = now.In(s.Loc)
	return time.Date(now.Year(), now.Month(), now.Day(), s.Hour, s.Minute, 0, 0, s.Loc)
}

// MarkRan запоминает запуск, сделанный вне расписания (например, при старте).
func (s *Schedule) MarkRan(t time.Time) { s.ranAt = t }

// Tick запускает задачу, если время пришло, а после целевого момента она ещё не запускалась.
func (s *Schedule) Tick(ctx context.Context) (bool, error) {
	now := s.now()
	target := s.Target(now)
	if now.Before(target) {
		return false, nil
	}

	last := s.ranAt
	if s.LastRun != nil {
		fromDB, err := s.LastRun(ctx)
		if err != nil {
			// Не знаем, запускалась ли задача: безопаснее пропустить тик, чем запустить второй раз.
			return false, fmt.Errorf("не прочитался последний запуск: %w", err)
		}
		if fromDB.After(last) {
			last = fromDB
		}
	}
	if !last.Before(target) {
		return false, nil
	}

	s.ranAt = now
	s.Run(ctx)
	return true, nil
}

// Loop проверяет расписание каждые every, пока не отменён контекст.
func (s *Schedule) Loop(ctx context.Context, every time.Duration, onError func(error)) {
	ticker := time.NewTicker(every)
	defer ticker.Stop()

	for {
		if _, err := s.Tick(ctx); err != nil && onError != nil {
			onError(err)
		}

		select {
		case <-ticker.C:
		case <-ctx.Done():
			return
		}
	}
}
