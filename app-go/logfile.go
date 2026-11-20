package main

import (
	"context"
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"time"
)

const (
	logRetention = 7 * 24 * time.Hour
	logBackups   = 7
	logTimestamp = "2006-01-02T15-04-05.000000000"
)

// logFile keeps a bounded journal for the admin panel. The lock has a stable
// path so scheduled and manually started collectors can safely share the file.
type logFile struct {
	path     string
	maxBytes int64
	console  io.Writer
}

func (l logFile) Write(entry []byte) (int, error) {
	if _, err := l.console.Write(entry); err != nil {
		return 0, err
	}
	if err := l.append(entry, time.Now()); err != nil {
		fmt.Fprintln(os.Stderr, "журнал сборщика:", err)
	}
	return len(entry), nil
}

func (l logFile) append(entry []byte, now time.Time) error {
	if int64(len(entry)) > l.maxBytes {
		return errors.New("запись превышает размер файла журнала")
	}
	return l.withLock(func() error {
		info, err := os.Stat(l.path)
		if err != nil && !errors.Is(err, os.ErrNotExist) {
			return err
		}
		today := now.UTC().Truncate(24 * time.Hour)
		rotated := info != nil && (info.Size()+int64(len(entry)) > l.maxBytes || info.ModTime().Before(today))
		if rotated {
			backup := strings.TrimSuffix(l.path, ".log") + "-" + now.UTC().Format(logTimestamp) + ".log"
			if err := os.Rename(l.path, backup); err != nil {
				return err
			}
		}

		file, err := os.OpenFile(l.path, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0o600)
		if err != nil {
			return err
		}
		_, writeErr := file.Write(entry)
		result := errors.Join(writeErr, file.Close())
		if rotated {
			result = errors.Join(result, l.prune(now))
		}
		return result
	})
}

func (l logFile) withLock(action func() error) error {
	lock, err := os.OpenFile(l.path+".lock", os.O_CREATE|os.O_RDWR, 0o600)
	if err != nil {
		return err
	}
	defer lock.Close()
	if err := syscall.Flock(int(lock.Fd()), syscall.LOCK_EX); err != nil {
		return err
	}
	return action()
}

func (l logFile) maintain(ctx context.Context) {
	ticker := time.NewTicker(time.Hour)
	defer ticker.Stop()
	for {
		if err := l.withLock(func() error { return l.prune(time.Now()) }); err != nil {
			fmt.Fprintln(os.Stderr, "очистка журнала сборщика:", err)
		}
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
		}
	}
}

// prune runs with the journal lock held. Only this collector's files are eligible.
func (l logFile) prune(now time.Time) error {
	dir := filepath.Dir(l.path)
	entries, err := os.ReadDir(dir)
	if err != nil {
		return err
	}
	cutoff := now.Add(-logRetention)
	filename := filepath.Base(l.path)
	prefix := strings.TrimSuffix(filename, ".log") + "-"
	kept := 0
	var result error
	for i := len(entries) - 1; i >= 0; i-- {
		entry := entries[i]
		stamp, ok := strings.CutPrefix(entry.Name(), prefix)
		if !ok || !strings.HasSuffix(stamp, ".log") {
			continue
		}
		if _, err := time.Parse(logTimestamp, strings.TrimSuffix(stamp, ".log")); err != nil {
			continue
		}
		removed, err := removeLog(entry, dir, cutoff, kept >= logBackups)
		result = errors.Join(result, err)
		if !removed {
			kept++
		}
	}
	for _, entry := range entries {
		if entry.Name() == filename || entry.Name() == filename+".1" {
			_, err := removeLog(entry, dir, cutoff, entry.Name() != filename && kept >= logBackups)
			result = errors.Join(result, err)
		}
	}
	return result
}

func removeLog(entry os.DirEntry, dir string, cutoff time.Time, overLimit bool) (bool, error) {
	if !entry.Type().IsRegular() {
		return false, nil
	}
	info, err := entry.Info()
	if err != nil || (!overLimit && info.ModTime().After(cutoff)) {
		return false, err
	}
	err = os.Remove(filepath.Join(dir, entry.Name()))
	return err == nil, err
}
