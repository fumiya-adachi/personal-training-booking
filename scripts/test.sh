#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
export LOCAL_UID="${LOCAL_UID:-$(id -u)}"
export LOCAL_GID="${LOCAL_GID:-$(id -g)}"
docker compose run --rm --no-deps app php artisan test
docker compose run --rm --no-deps node npm run typecheck
docker compose run --rm --no-deps node npm run build
