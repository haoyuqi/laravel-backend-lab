#!/usr/bin/env bash
# Verifies the published application image end to end.
#
# The script expects the database and Redis to be reachable on the runner
# network and uses host networking for the application container.

set -euo pipefail

IMAGE="${IMAGE:-laravel-backend-lab:ci}"
APP_URL="${APP_URL:-http://127.0.0.1:8080}"
APP_KEY="${APP_KEY:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-laravel}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"
VOLUME="${VOLUME:-lab-ci-storage}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-ci-admin-password}"

psql_query() {
    docker run --rm --network host -e PGPASSWORD="$DB_PASSWORD" --entrypoint psql "$IMAGE" \
        -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -tAc "$1"
}

wait_for_up() {
    local attempts="${1:-60}"

    for _ in $(seq 1 "$attempts"); do
        if curl -fsS "$APP_URL/up" > /dev/null 2>&1; then
            return 0
        fi

        sleep 2
    done

    docker logs lab-app || true

    return 1
}

start_app() {
    test -n "$APP_KEY"
    test -n "$DB_PASSWORD"

    docker run -d --name lab-app --network host \
        -v "$VOLUME:/var/www/html/storage" \
        -e APP_KEY="$APP_KEY" \
        -e APP_ENV=production \
        -e APP_DEBUG=false \
        -e APP_URL="$APP_URL" \
        -e ADMIN_EMAILS="$ADMIN_EMAIL" \
        -e DB_CONNECTION=pgsql \
        -e DB_HOST="$DB_HOST" \
        -e DB_PORT="$DB_PORT" \
        -e DB_DATABASE="$DB_DATABASE" \
        -e DB_USERNAME="$DB_USERNAME" \
        -e DB_PASSWORD="$DB_PASSWORD" \
        -e REDIS_HOST="$REDIS_HOST" \
        -e REDIS_PORT="$REDIS_PORT" \
        -e CACHE_STORE=redis \
        -e SESSION_DRIVER=file \
        -e QUEUE_CONNECTION=redis \
        -e BROADCAST_CONNECTION=log \
        "$IMAGE" > /dev/null
}

verify_image() {
    test "$(docker run --rm --entrypoint sh "$IMAGE" -c 'id -u')" = "82"

    docker run --rm --entrypoint sh "$IMAGE" -c '
        set -eu

        for extension in intl zip bcmath redis pcntl posix pdo_mysql pdo_pgsql pdo_sqlite dom xmlreader; do
            php -m | grep -qix "$extension"
        done

        php -m | grep -qix "Zend OPcache"
        php -r "exit(filter_var(ini_get(\"expose_php\"), FILTER_VALIDATE_BOOLEAN) || ini_get(\"opcache.validate_timestamps\") !== \"0\" ? 1 : 0);"

        test ! -e .env
        ! find . -maxdepth 1 -name ".env.*" ! -name ".env.example" | grep -q .
        test -z "$(find storage -maxdepth 2 -name "*.key" | head -n 1)"
        test -z "$(find bootstrap/cache -maxdepth 1 -name "*.php" | head -n 1)"
        test ! -e public/hot

        for binary in mysqldump pg_dump sqlite3; do
            command -v "$binary" > /dev/null
        done
    '
}

verify_startup() {
    psql_query "create table sentinel (id integer primary key, note text not null)"
    psql_query "insert into sentinel values (1, 'untouched')"

    start_app
    wait_for_up

    test "$(psql_query "select note from sentinel where id = 1")" = "untouched"
    test -z "$(psql_query "select to_regclass('public.migrations')")"
    test -z "$(psql_query "select to_regclass('public.users')")"

    docker exec lab-app test ! -e .env
    test -z "$(docker exec lab-app sh -c 'find storage -maxdepth 2 -name "*.key" | head -n 1')"

    actual_key="$(docker exec lab-app php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo config("app.key");
    ')"
    test "$actual_key" = "$APP_KEY"

    docker restart lab-app > /dev/null
    wait_for_up

    test -z "$(psql_query "select to_regclass('public.migrations')")"
    test -z "$(psql_query "select to_regclass('public.users')")"
}

verify_runtime() {
    docker exec lab-app php artisan migrate --force
    test "$(psql_query "select count(*) from users")" = "0"

    docker exec lab-app php artisan make:filament-user \
        --name=Admin --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" > /dev/null

    docker exec lab-app php artisan backup:run
    test -n "$(docker exec lab-app sh -c 'find storage/backup -name "*.zip" | head -n 1')"

    test "$(curl -s -o /dev/null -w '%{http_code}' "$APP_URL/")" = "200"
    test "$(curl -s -o /dev/null -w '%{http_code}' "$APP_URL/admin/login")" = "200"
    test "$(curl -s -o /dev/null -w '%{http_code}' "$APP_URL/admin")" = "302"

    curl -fsS "$APP_URL/health/ready" -o /tmp/health.json
    grep -q '"database":"healthy"' /tmp/health.json
    grep -q '"redis":"healthy"' /tmp/health.json

    docker exec lab-app php artisan schedule:list > /tmp/schedule-list.txt
    grep -q 'backup:run' /tmp/schedule-list.txt

    set +e
    docker exec lab-app sh -c 'timeout -s TERM 10 php artisan schedule:work' > /tmp/schedule-work.log 2>&1
    status=$?
    set -e
    test "$status" = "0" || test "$status" = "124" || test "$status" = "143"

    docker exec lab-app php artisan tinker --execute='dispatch(new App\Jobs\QueueJob(3));' > /dev/null
    docker exec lab-app php artisan queue:work --queue=queue-test --stop-when-empty --tries=1 > /dev/null
    test "$(docker exec lab-app php artisan tinker --execute='echo Cache::has("key:0") ? "processed" : "missing";' | tail -n 1)" = "processed"

    set +e
    docker exec lab-app sh -c 'timeout -s TERM 15 php artisan horizon' > /tmp/horizon.log 2>&1
    status=$?
    set -e
    test "$status" = "0" || test "$status" = "124" || test "$status" = "143"
    grep -q 'Horizon started successfully' /tmp/horizon.log

    docker run -d --name lab-reverb -p 18081:8080 \
        -e APP_KEY="$APP_KEY" \
        -e APP_ENV=production \
        -e DB_CONNECTION=sqlite \
        -e DB_DATABASE=/var/www/html/storage/app/reverb.sqlite \
        -e CACHE_STORE=file \
        -e SESSION_DRIVER=file \
        -e QUEUE_CONNECTION=sync \
        -e BROADCAST_CONNECTION=reverb \
        -e REVERB_APP_ID=ci \
        -e REVERB_APP_KEY=ci-key \
        -e REVERB_APP_SECRET=ci-secret \
        -e REVERB_HOST=127.0.0.1 \
        -e REVERB_PORT=8080 \
        -e REVERB_SCHEME=http \
        -e REVERB_SERVER_HOST=0.0.0.0 \
        -e REVERB_SERVER_PORT=8080 \
        "$IMAGE" php artisan reverb:start --host=0.0.0.0 --port=8080 > /dev/null

    for _ in $(seq 1 30); do
        if curl -fsS http://127.0.0.1:18081/up > /dev/null 2>&1; then
            break
        fi

        sleep 2
    done

    curl -fsS http://127.0.0.1:18081/up -o /tmp/reverb-health.json
    grep -q '"health":"OK"' /tmp/reverb-health.json
    docker exec lab-reverb php artisan tinker --execute='event(new App\Events\PushTimeEvent);' > /dev/null
    docker rm -f lab-reverb > /dev/null
}

verify_persistence() {
    docker exec lab-app sh -c 'echo persistent > storage/app/marker.txt'
    docker rm -f lab-app > /dev/null

    start_app
    wait_for_up

    test "$(docker exec lab-app cat storage/app/marker.txt)" = "persistent"

    started_at="$(date +%s)"
    docker stop -t 30 lab-app > /dev/null
    finished_at="$(date +%s)"
    test $((finished_at - started_at)) -lt 30

    docker rm -f lab-app > /dev/null
}

case "${1:-all}" in
    verify-image) verify_image ;;
    verify-startup) verify_startup ;;
    verify-runtime) verify_runtime ;;
    verify-persistence) verify_persistence ;;
    all)
        verify_image
        verify_startup
        verify_runtime
        verify_persistence
        ;;
    *)
        echo "Unknown command: $1" >&2
        exit 2
        ;;
esac
