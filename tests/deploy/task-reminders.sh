#!/usr/bin/env bash
# No application/database/network calls: php is replaced with a shell function.
set -Eeuo pipefail
bash -n deploy/start.sh
bash -n deploy/task-reminder-worker.sh
task_tmp=$(mktemp -d)
export REMINDER_TEST_LOG="$task_tmp/commands.log"
export REMINDER_TEST_PID="$task_tmp/server.pid"
start_pid=''
cleanup() {
    if [[ -n "$start_pid" ]]; then kill "$start_pid" 2>/dev/null || true; wait "$start_pid" 2>/dev/null || true; fi
    rm -f -- "$REMINDER_TEST_LOG" "$REMINDER_TEST_PID"
    rmdir -- "$task_tmp"
}
trap cleanup EXIT
php() {
    printf '%s\n' "$*" >> "$REMINDER_TEST_LOG"
    if [[ "${2:-}" == 'serve' ]]; then
        printf '%s' "$BASHPID" > "$REMINDER_TEST_PID"
        exec sleep 60
    fi
}
export -f php
bash deploy/start.sh &
start_pid=$!
ready=false
for ((i=0;i<100;i++)); do
    if [[ -f "$REMINDER_TEST_PID" ]] && grep -q 'tasks:send-overdue-reminders --scheduled' "$REMINDER_TEST_LOG"; then ready=true; break; fi
    sleep 0.1
done
[[ "$ready" == true ]]
! grep -q 'schedule:work\|schedule:run\|accounts:block' "$REMINDER_TEST_LOG"
server_pid=$(<"$REMINDER_TEST_PID")
kill -0 "$server_pid"
kill -TERM "$start_pid"
wait "$start_pid" || true
start_pid=''
! kill -0 "$server_pid" 2>/dev/null
echo 'Startup launches web and reminder workers; SIGTERM stops both safely.'
