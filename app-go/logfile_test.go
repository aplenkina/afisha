package main

import (
	"fmt"
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestLogFileRotatesBySizeAndDay(t *testing.T) {
	dir := t.TempDir()
	journal := logFile{path: filepath.Join(dir, "collector.log"), maxBytes: 8}
	now := time.Now().UTC()
	if err := journal.append([]byte("first\n"), now); err != nil {
		t.Fatal(err)
	}
	if err := journal.append([]byte("next\n"), now); err != nil {
		t.Fatal(err)
	}

	// This write would still fit in the active file. The change of date forces rotation.
	tomorrow := now.Add(24 * time.Hour)
	if err := journal.append([]byte("ok\n"), tomorrow); err != nil {
		t.Fatal(err)
	}

	for name, want := range map[string]string{
		"collector-" + now.Format(logTimestamp) + ".log":      "first\n",
		"collector-" + tomorrow.Format(logTimestamp) + ".log": "next\n",
		"collector.log": "ok\n",
	} {
		got, err := os.ReadFile(filepath.Join(dir, name))
		if err != nil || string(got) != want {
			t.Errorf("journal %s = %q, %v; want %q", name, got, err, want)
		}
	}
}

func TestLogFilePrunesExpiredFilesAndCapsBackups(t *testing.T) {
	dir := t.TempDir()
	journal := logFile{path: filepath.Join(dir, "collector.log")}
	now := time.Now().UTC()
	cutoff := now.Add(-logRetention)
	write := func(name string, at time.Time) string {
		t.Helper()
		path := filepath.Join(dir, name)
		if err := os.WriteFile(path, []byte("record\n"), 0o600); err != nil {
			t.Fatal(err)
		}
		if err := os.Chtimes(path, at, at); err != nil {
			t.Fatal(err)
		}
		return path
	}

	expired := []string{
		write("collector.log", cutoff.Add(-time.Hour)),
		write("collector.log.1", cutoff.Add(-time.Hour)),
		write("collector-"+cutoff.Format(logTimestamp)+".log", cutoff),
	}
	kept := []string{
		write("external-monitor.json", cutoff),
		write("php.log", cutoff),
		write("collector-manual.log", cutoff),
	}
	for i := 0; i <= logBackups; i++ {
		stamp := now.Add(time.Duration(i-logBackups) * time.Minute)
		path := write(fmt.Sprintf("collector-%s.log", stamp.Format(logTimestamp)), stamp)
		if i == 0 {
			expired = append(expired, path)
		} else {
			kept = append(kept, path)
		}
	}

	if err := journal.withLock(func() error { return journal.prune(now) }); err != nil {
		t.Fatal(err)
	}
	for _, path := range expired {
		if _, err := os.Stat(path); !os.IsNotExist(err) {
			t.Errorf("expired log still exists: %s (%v)", path, err)
		}
	}
	for _, path := range kept {
		if _, err := os.Stat(path); err != nil {
			t.Errorf("expected to retain %s: %v", path, err)
		}
	}
}
