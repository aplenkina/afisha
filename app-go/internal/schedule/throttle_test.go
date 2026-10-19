package schedule

import (
	"errors"
	"testing"
	"time"
)

func TestThrottleErrors(t *testing.T) {
	now := time.Date(2026, 10, 19, 12, 0, 0, 0, time.UTC)
	var logged []string
	throttled := ThrottleErrors(10*time.Minute, func() time.Time { return now }, func(err error) {
		logged = append(logged, err.Error())
	})

	throttled(errors.New("база недоступна"))
	now = now.Add(30 * time.Second)
	throttled(errors.New("база недоступна"))
	now = now.Add(9 * time.Minute)
	throttled(errors.New("база недоступна"))
	if len(logged) != 1 {
		t.Fatalf("одинаковая ошибка внутри окна не должна повторяться: %v", logged)
	}

	throttled(errors.New("другая ошибка"))
	if len(logged) != 2 || logged[1] != "другая ошибка" {
		t.Fatalf("новая ошибка должна логироваться сразу: %v", logged)
	}

	now = now.Add(11 * time.Minute)
	throttled(errors.New("другая ошибка"))
	if len(logged) != 3 {
		t.Fatalf("после окна ошибка должна напомнить о себе: %v", logged)
	}
}

func TestThrottleErrorsAfterRecoveryLogsSameErrorAgain(t *testing.T) {
	now := time.Date(2026, 10, 19, 12, 0, 0, 0, time.UTC)
	var logged int
	throttled := ThrottleErrors(10*time.Minute, func() time.Time { return now }, func(error) { logged++ })

	throttled(errors.New("a"))
	throttled(errors.New("b"))
	throttled(errors.New("a"))

	if logged != 3 {
		t.Fatalf("чередующиеся ошибки логируются каждая: %d", logged)
	}
}
