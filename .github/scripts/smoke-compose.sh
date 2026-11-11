#!/usr/bin/env bash
# Runs only against unique disposable projects, volumes, and a source copy.
set -Eeuo pipefail
trap 'echo "Compose smoke failed at line $LINENO" >&2' ERR

mode=${1:?Usage: smoke-compose.sh production|development}
case "$mode" in production|development) ;; *) exit 2 ;; esac
root=$(cd "$(dirname "$0")/../.." && pwd)
scratch=$(mktemp -d "${TMPDIR:-/tmp}/backend-lab-compose.XXXXXXXX")
project="lab-compose-${mode}-$(date +%s)-$$"
dependencies="${project}-external"
export COMPOSE_ENV_FILE="$scratch/runtime.env"
export COMPOSE_PROFILES=database,redis,realtime
export APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
export DB_PASSWORD="$(openssl rand -hex 24)"
export DEV_UID="$(id -u)" DEV_GID="$(id -g)"
export DEV_IMAGE_TAG="ci-${project}"
export HTTP_PORT=$((20000 + $$ % 10000))
export VITE_PORT=$((HTTP_PORT + 1)) REVERB_PUBLIC_PORT=$((HTTP_PORT + 2))
export APP_IMAGE=${APP_IMAGE:-backend-lab-compose}
export VERSION=${VERSION:-ci}
if [[ "${GITHUB_ACTIONS:-}" == true ]]; then
  echo "::add-mask::$APP_KEY"
  echo "::add-mask::$DB_PASSWORD"
fi

# Avoid modifying the checkout's hot file, generated assets, or local secrets.
mkdir "$scratch/source"
(cd "$root" && git ls-files -z | tar --null -T - -cf -) | tar -xf - -C "$scratch/source"
for path in compose.yaml compose.dev.yaml .env.compose.example .env.compose.dev.example docker/php-dev.ini; do
  cp "$root/$path" "$scratch/source/$path"
done
cd "$scratch/source"
file=compose.yaml
template=.env.compose.example
if [[ "$mode" == development ]]; then
  file=compose.dev.yaml
  template=.env.compose.dev.example
fi
sed -e "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" \
    -e "s|^APP_URL=.*|APP_URL=http://127.0.0.1:$HTTP_PORT|" \
    -e "s|^VITE_PORT=.*|VITE_PORT=$VITE_PORT|" \
    -e 's|^VITE_HMR_HOST=.*|VITE_HMR_HOST=127.0.0.1|' \
    -e "s|^REVERB_CLIENT_PORT=.*|REVERB_CLIENT_PORT=$REVERB_PUBLIC_PORT|" \
    -e 's|^REVERB_CLIENT_HOST=.*|REVERB_CLIENT_HOST=127.0.0.1|' \
    -e 's|^BROADCAST_CONNECTION=.*|BROADCAST_CONNECTION=reverb|' \
    -e 's|^REVERB_APP_KEY=.*|REVERB_APP_KEY=compose-ci-public|' \
    -e "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$(openssl rand -hex 24)|" \
    "$template" > "$COMPOSE_ENV_FILE"
chmod 600 "$COMPOSE_ENV_FILE"

dc() { docker compose --env-file "$COMPOSE_ENV_FILE" -f "$file" -p "$project" "$@"; }
ext() { docker compose --env-file "$COMPOSE_ENV_FILE" -f "$file" -p "$dependencies" "$@"; }
cleanup() {
  local result=$?
  trap - EXIT
  ext --profile database --profile redis --profile realtime down --volumes --remove-orphans >/dev/null 2>&1 || true
  dc --profile database --profile redis --profile realtime down --volumes --remove-orphans >/dev/null 2>&1 || true
  if [[ "$mode" == development ]]; then
    docker image rm "backend-lab-dev:$DEV_IMAGE_TAG" >/dev/null 2>&1 || true
  fi
  # This is the validated mktemp directory owned by this test invocation only.
  case "$scratch" in */backend-lab-compose.*) rm -r -- "$scratch" ;; esac
  exit "$result"
}
trap cleanup EXIT
run() {
  echo "Compose check: ${1:-} ${2:-} ${3:-}" >&2
  dc run --rm --no-deps -T app "$@"
}
wait_http() {
  local url=$1
  for _ in $(seq 1 60); do
    if curl -fsS "$url" >/dev/null 2>&1; then return; fi
    sleep 2
  done
  echo "Timed out waiting for a Compose endpoint" >&2
  return 1
}
url_for() { printf 'http://%s' "$(dc port "$1" "$2" | head -1)"; }

# Parse each profile combination without printing resolved credentials.
for profiles in '' database redis database,redis database,redis,realtime; do
  COMPOSE_PROFILES="$profiles" dc config --quiet
done
if [[ "$mode" == development ]]; then
  dc build app
  test_image="backend-lab-dev:$DEV_IMAGE_TAG"
else
  docker image inspect "$APP_IMAGE:$VERSION" >/dev/null
  test_image="$APP_IMAGE:$VERSION"
fi
# Exercise the documented first-install helper and prove reruns retain secrets.
cp "$template" "$scratch/initialize.env"
chmod 600 "$scratch/initialize.env"
initialize() {
  docker run --rm -i --user "$(id -u):$(id -g)" --entrypoint php \
    -v "$scratch/initialize.env:/deployment.env" "$test_image" \
    < "$root/docker/compose-init.php" >/dev/null
}
initialize
grep -q '^APP_KEY=base64:.' "$scratch/initialize.env"
cp "$scratch/initialize.env" "$scratch/initialized.env"
initialize
cmp -s "$scratch/initialize.env" "$scratch/initialized.env"
dc up -d --wait database redis
run php artisan migrate --force >/dev/null
run php artisan make:filament-user --name=ComposeAdmin --email=admin@example.com --password=compose-ci-only >/dev/null
if [[ "$mode" == development ]]; then
  run php artisan filament:assets >/dev/null
  # The public bind starts as a clean source tree; recreate its storage link.
  run php artisan storage:link >/dev/null
fi
run php artisan tinker --execute='file_put_contents(storage_path("app/public/compose-sentinel.txt"), "preserved");' >/dev/null
dc up -d app worker scheduler reverb
app_url=$(url_for app 8080)
if [[ "$mode" == development ]]; then
  dc up -d vite
  wait_http "$(url_for vite 5173)/@vite/client"
fi
wait_http "$app_url/up"
wait_http "$app_url/health/ready"
test "$(curl -s -o /dev/null -w '%{http_code}' "$app_url/admin/login")" = 200
test "$(curl -fsS "$app_url/storage/compose-sentinel.txt")" = preserved
run php artisan tinker --execute='dispatch(new App\Jobs\QueueJob(1));' >/dev/null
for _ in $(seq 1 30); do
  if [[ "$(run php artisan tinker --execute='echo Cache::get("key:0") === "value:0" ? "processed" : "missing";' | tail -1)" == processed ]]; then break; fi
  sleep 2
done
test "$(run php artisan tinker --execute='echo Cache::get("key:0") === "value:0" ? "processed" : "missing";' | tail -1)" = processed
dc exec -T scheduler php artisan schedule:list >/dev/null
wait_http "$(url_for reverb 8080)/up"
dc exec -T reverb php artisan tinker --execute='event(new App\Events\PushTimeEvent);' >/dev/null

if [[ "$mode" == development ]]; then
  dc exec -T app php -r 'exit(ini_get("opcache.validate_timestamps") === "1" ? 0 : 1);'
  # Verify source edits are visible without rebuilding and dev dependencies exist.
  printf '\n<!-- compose-source-sentinel -->\n' >> resources/views/index/welcome.blade.php
  curl -fsS "$app_url/" | grep -q compose-source-sentinel
  dc exec -T app test -f vendor/phpunit/phpunit/phpunit
else
  dc exec -T app test ! -f vendor/phpunit/phpunit/phpunit
fi

if [[ "${COMPOSE_BROWSER_CHECK:-false}" == true ]]; then
  (cd "$root" && APP_URL="$app_url" COMPOSE_SMOKE_PROJECT="$project" \
    COMPOSE_SMOKE_FILE="$scratch/source/$file" COMPOSE_SMOKE_ENV="$COMPOSE_ENV_FILE" \
    CONTAINER_ADMIN_PASSWORD=compose-ci-only \
    npx playwright test --config=tests/e2e/playwright.compose.config.ts)
fi

dc up -d --force-recreate app
app_url=$(url_for app 8080)
wait_http "$app_url/up"
test "$(curl -fsS "$app_url/storage/compose-sentinel.txt")" = preserved
test "$(run php artisan tinker --execute='echo App\Models\User::where("email", "admin@example.com")->count();' | tail -1)" = 1

# Connect to separately owned dependencies with both local profiles disabled.
# Existing test dependencies stay alive until the EXIT cleanup; no shared service
# or existing developer database is used.
dc down >/dev/null
ext up -d --wait database redis
dc create app >/dev/null
docker network connect --alias external-database "${project}_default" "$(ext ps -q database)"
docker network connect --alias external-redis "${project}_default" "$(ext ps -q redis)"
for combination in external local-database local-redis; do
  export COMPOSE_PROFILES=
  database_host=external-database
  redis_host=external-redis
  case "$combination" in
    local-database)
      export COMPOSE_PROFILES=database
      database_host=database
      dc up -d --wait database
      ;;
    local-redis)
      export COMPOSE_PROFILES=redis
      redis_host=redis
      dc up -d --wait redis
      ;;
  esac
  sed -i.bak -e "s/^DB_HOST=.*/DB_HOST=$database_host/" \
      -e "s/^REDIS_HOST=.*/REDIS_HOST=$redis_host/" "$COMPOSE_ENV_FILE"
  run php artisan migrate --force >/dev/null
  dc up -d --force-recreate app
  [[ "$database_host" == database ]] || test -z "$(dc ps -q database)"
  [[ "$redis_host" == redis ]] || test -z "$(dc ps -q redis)"
  app_url=$(url_for app 8080)
  wait_http "$app_url/health/ready"
  curl -fsS "$app_url/health/ready" | grep -q '"redis":"healthy"'
  echo "Compose $mode: $combination connection check passed."
  dc --profile database --profile redis rm -sf database redis >/dev/null
done
echo "Compose $mode: all dependency combinations, jobs, endpoints, and persistence passed."
