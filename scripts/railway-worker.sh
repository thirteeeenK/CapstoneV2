#!/usr/bin/env bash
set -euo pipefail

php artisan queue:work database --sleep=3 --tries=3 --timeout=60 &
queue_pid=$!
php artisan schedule:work &
scheduler_pid=$!

stop_workers() {
    kill -TERM "$queue_pid" "$scheduler_pid" 2>/dev/null || true
    wait "$queue_pid" "$scheduler_pid" 2>/dev/null || true
}
trap stop_workers EXIT
trap 'exit 0' INT TERM

set +e
wait -n "$queue_pid" "$scheduler_pid"
worker_status=$?
if [ "$worker_status" -eq 0 ]; then
    worker_status=1
fi
exit "$worker_status"
