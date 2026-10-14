#!/usr/bin/env bash
set -Eeuo pipefail

php artisan storage:link --force
php artisan migrate --force
php artisan db:seed --class=SuperAdminSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-2}"
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}:/app/deploy/php"

web_pid=''
reminder_pid=''
stop() {
    trap - TERM INT EXIT
    for pid in "$web_pid" "$reminder_pid"; do
        if [[ -n "$pid" ]]; then kill "$pid" 2>/dev/null || true; fi
    done
    wait || true
}
trap stop TERM INT EXIT

php artisan serve --no-reload --host=0.0.0.0 --port="${PORT:-8000}" &
web_pid=$!
# Start only reminders, not unrelated account/security schedules.
bash deploy/task-reminder-worker.sh &
reminder_pid=$!

set +e
wait -n "$web_pid" "$reminder_pid"
status=$?
stop
# If either child unexpectedly exits, restart the entire container.
if [[ "$status" -eq 0 ]]; then status=1; fi
exit "$status"
