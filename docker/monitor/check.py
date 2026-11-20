import json
import os
import sys
import time
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


def probe(url):
    try:
        with urlopen(url, timeout=10) as response:
            return response.status == 200
    except (HTTPError, URLError, TimeoutError):
        return False


def notify(token, chat, text):
    body = json.dumps({"chat_id": chat, "text": text}).encode()
    request = Request(
        f"https://api.telegram.org/bot{token}/sendMessage",
        data=body,
        headers={"Content-Type": "application/json"},
    )
    try:
        with urlopen(request, timeout=10) as response:
            return json.load(response).get("ok") is True
    except (HTTPError, URLError, TimeoutError, ValueError):
        return False


def report(base, token, status):
    request = Request(
        base + "/healthz/report",
        data=json.dumps(status).encode(),
        headers={"Content-Type": "application/json"},
    )
    request.add_unredirected_header("Authorization", f"Bearer {token}")
    try:
        with urlopen(request, timeout=10) as response:
            return response.status == 204 and response.url == request.full_url
    except (HTTPError, URLError, TimeoutError):
        return False


def main():
    os.umask(0o077)
    base = os.environ["AFISHA_MONITOR_URL"].rstrip("/")
    state_file = Path(os.environ["AFISHA_MONITOR_STATE"])
    token = os.environ["AFISHA_MONITOR_BOT_TOKEN"]
    chat = os.environ["AFISHA_MONITOR_CHAT_ID"]
    report_token = os.environ.get("AFISHA_MONITOR_REPORT_TOKEN")
    state = json.loads(state_file.read_text()) if state_file.exists() else {}
    checks = {
        "сайт": probe(base + "/") and probe(base + "/healthz"),
    }
    if checks["сайт"]:
        checks["импорт событий"] = probe(base + "/healthz/import")
    failed_to_notify = False
    for label, healthy in checks.items():
        entry = state.setdefault(label, {"failures": 0, "alerted": False})
        if label == "сайт" and not healthy:
            entry["last_failure_at"] = int(time.time())
        entry["failures"] = 0 if healthy else min(entry["failures"] + 1, 2)
        if (not healthy and entry["failures"] >= 2 and not entry["alerted"]) or (healthy and entry["alerted"]):
            status = "восстановлен" if healthy else "недоступен"
            if notify(token, chat, f"Афиша РБ: {label} — {status}.\n{base}"):
                entry["alerted"] = not healthy
            else:
                failed_to_notify = True
    temp = state_file.with_suffix(".tmp")
    temp.write_text(json.dumps(state))
    temp.replace(state_file)
    if report_token:
        delivered = report(base, report_token, {
            "siteHealthy": checks["сайт"],
            "importHealthy": checks.get("импорт событий"),
            "lastSiteFailureAt": state["сайт"].get("last_failure_at"),
        })
        if not delivered and checks["сайт"]:
            print("Не удалось передать отчёт в админку.", file=sys.stderr)
    if failed_to_notify:
        print("Не удалось отправить уведомление Telegram.", file=sys.stderr)
    return int(failed_to_notify)


if __name__ == "__main__":
    sys.exit(main())
