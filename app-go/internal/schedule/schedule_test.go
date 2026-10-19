package schedule

import (
	"context"
	"errors"
	"testing"
	"time"
)

var minsk = time.FixedZone("Minsk", 3*3600)

func at(day, hour, minute int) time.Time {
	return time.Date(2026, 10, day, hour, minute, 0, 0, minsk)
}

type fixture struct {
	now    time.Time
	dbLast time.Time
	dbErr  error
	runs   int
	sched  *Schedule
}

func newFixture(now time.Time) *fixture {
	f := &fixture{now: now}
	f.sched = &Schedule{
		Hour: 4, Minute: 0, Loc: minsk,
		Now:     func() time.Time { return f.now },
		LastRun: func(context.Context) (time.Time, error) { return f.dbLast, f.dbErr },
		Run:     func(context.Context) { f.runs++ },
	}
	return f
}

func (f *fixture) tick(t *testing.T) bool {
	t.Helper()
	ran, err := f.sched.Tick(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	return ran
}

func TestDoesNotRunBeforeTarget(t *testing.T) {
	f := newFixture(at(18, 3, 59))
	if f.tick(t) || f.runs != 0 {
		t.Error("до 04:00 запускать нельзя")
	}
}

func TestRunsOnceAfterTarget(t *testing.T) {
	f := newFixture(at(18, 4, 0))
	if !f.tick(t) {
		t.Fatal("в 04:00 нужно запустить")
	}

	for _, m := range []int{1, 30, 600} {
		f.now = at(18, 4, 0).Add(time.Duration(m) * time.Minute)
		if f.tick(t) {
			t.Errorf("повторный запуск через %d мин в тот же день", m)
		}
	}
	if f.runs != 1 {
		t.Errorf("запусков %d, ожидали 1", f.runs)
	}
}

func TestRunsAgainNextDay(t *testing.T) {
	f := newFixture(at(18, 4, 0))
	f.tick(t)

	f.now = at(19, 3, 0)
	if f.tick(t) {
		t.Error("19-го до 04:00 запускать рано")
	}
	f.now = at(19, 4, 0)
	if !f.tick(t) || f.runs != 2 {
		t.Errorf("19-го в 04:00 должен быть второй запуск, запусков %d", f.runs)
	}
}

func TestCatchesUpMissedRunWhenStartedLate(t *testing.T) {
	f := newFixture(at(18, 12, 56))
	f.dbLast = at(17, 17, 28)

	if !f.tick(t) {
		t.Fatal("пропущенный запуск должен догоняться")
	}
}

func TestRestartAfterTodaysRunDoesNotRunAgain(t *testing.T) {
	f := newFixture(at(18, 17, 0))
	f.dbLast = at(18, 4, 0).Add(2 * time.Minute)

	if f.tick(t) || f.runs != 0 {
		t.Error("сегодня уже собирали, запуск не нужен")
	}
}

func TestUsesWallClockNotElapsedTime(t *testing.T) {
	f := newFixture(at(17, 22, 58))
	f.dbLast = at(17, 17, 28)
	if f.tick(t) {
		t.Fatal("17-го после 04:00 запуск уже был")
	}

	f.now = at(18, 9, 28)
	if !f.tick(t) {
		t.Error("после пробуждения должен пройти запуск за 18-е")
	}
}

func TestDBErrorSkipsTickInsteadOfRunning(t *testing.T) {
	f := newFixture(at(18, 5, 0))
	f.dbErr = errors.New("база недоступна")

	ran, err := f.sched.Tick(context.Background())
	if ran || err == nil || f.runs != 0 {
		t.Errorf("при ошибке базы запуска быть не должно: ran=%v err=%v runs=%d", ran, err, f.runs)
	}
}

func TestMarkRanPreventsRunRightAfterStartupRun(t *testing.T) {
	f := newFixture(at(18, 17, 0))
	f.sched.MarkRan(f.now)

	if f.tick(t) {
		t.Error("после запуска при старте второй не нужен")
	}
}

func TestWorksWithoutLastRunSource(t *testing.T) {
	f := newFixture(at(18, 5, 0))
	f.sched.LastRun = nil

	if !f.tick(t) || f.tick(t) {
		t.Error("без базы: один запуск, дальше память процесса")
	}
}

func TestTargetUsesConfiguredZone(t *testing.T) {
	s := &Schedule{Hour: 4, Minute: 0, Loc: minsk}
	utc := time.Date(2026, 10, 18, 1, 30, 0, 0, time.UTC) // 04:30 в Минске

	if got := s.Target(utc); !got.Equal(at(18, 4, 0)) {
		t.Errorf("Target = %v", got)
	}
}
