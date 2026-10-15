#!/usr/bin/env bash
set -u
child=''
stop() {
    trap - TERM INT
    if [[ -n "$child" ]]; then kill "$child" 2>/dev/null || true; wait "$child" 2>/dev/null || true; fi
    exit 0
}
trap stop TERM INT
echo 'Task reminder worker started (Europe/Warsaw, 08:00–08:59).'
while true; do
    php artisan tasks:send-overdue-reminders --scheduled &
    child=$!
    wait "$child" || echo 'Task reminder check failed; inspect task_reminders logs.' >&2
    php artisan hr:send-vehicle-reminders --scheduled &
    child=$!
    wait "$child" || echo 'HR vehicle reminder check failed; inspect hr_vehicle_reminders logs.' >&2
    sleep 60 &
    child=$!
    wait "$child" || true
done
