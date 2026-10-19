package schedule

import "time"

func ThrottleErrors(every time.Duration, now func() time.Time, log func(error)) func(error) {
	var lastMessage string
	var lastAt time.Time

	return func(err error) {
		if err.Error() == lastMessage && now().Sub(lastAt) < every {
			return
		}
		lastMessage, lastAt = err.Error(), now()
		log(err)
	}
}
