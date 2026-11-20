#!/bin/sh
set -eu
umask 077

config=/tmp/afisha-alertmanager.yml
cat > "$config" <<'YAML'
route:
  receiver: resources
  group_by: [alertname, mountpoint]
  repeat_interval: 4h
receivers:
  - name: resources
YAML

if [ -n "$AFISHA_RESOURCE_BOT_TOKEN" ] || [ -n "$AFISHA_RESOURCE_CHAT_ID" ]; then
    : "${AFISHA_RESOURCE_BOT_TOKEN:?Set AFISHA_RESOURCE_BOT_TOKEN}"
    : "${AFISHA_RESOURCE_CHAT_ID:?Set AFISHA_RESOURCE_CHAT_ID}"
    case "${AFISHA_RESOURCE_CHAT_ID#-}" in
        ''|*[!0-9]*) echo 'AFISHA_RESOURCE_CHAT_ID must be a numeric chat ID' >&2; exit 1 ;;
    esac
    printf '%s\n' "$AFISHA_RESOURCE_BOT_TOKEN" > /tmp/afisha-telegram-token
    printf '%s\n' "$AFISHA_RESOURCE_CHAT_ID" > /tmp/afisha-telegram-chat-id
    cat >> "$config" <<'YAML'
    telegram_configs:
      - bot_token_file: /tmp/afisha-telegram-token
        chat_id_file: /tmp/afisha-telegram-chat-id
        send_resolved: true
        parse_mode: ''
        message: |-
          {{ range .Alerts.Firing }}Сбой · {{ .Annotations.summary }}
          {{ end }}{{ range .Alerts.Resolved }}Восстановлено · {{ .Annotations.summary }}
          {{ end }}
YAML
fi

exec /bin/alertmanager --config.file="$config" --storage.path=/alertmanager --cluster.listen-address=
