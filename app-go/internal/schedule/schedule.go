package schedule

import (
	"context"
	"fmt"
	"time"
)

// Сравниваем настенное время, а не таймер: монотонные часы стоят, пока Mac (и Docker-виртуалка) спит.
type Schedule struct {
	Hour, Minute int
	Loc          *time.Location
	Now          func() time.Time
	LastRun      func(ctx context.Context) (time.Time, error)
	Run          func(ctx context.Context)

	ranAt time.Time
}

func (s *Schedule) now() time.Time {
	if s.Now != nil {
		return s.Now().In(s.Loc)
	}
	return time.Now().In(s.Loc)
}

func (s *Schedule) Target(now time.Time) time.Time {
	now = now.In(s.Loc)
	return time.Date(now.Year(), now.Month(), now.Day(), s.Hour, s.Minute, 0, 0, s.Loc)
}

func (s *Schedule) MarkRan(t time.Time) { s.ranAt = t }

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
