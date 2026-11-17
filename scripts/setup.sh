#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."

if ! command -v docker >/dev/null 2>&1; then
  echo 'Docker Engine / Docker Desktop と Docker Compose v2 をインストールしてください。' >&2
  exit 1
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

# Linuxではホストユーザーと揃え、生成ファイルの所有者を保ちます。
export LOCAL_UID="${LOCAL_UID:-$(id -u)}"
export LOCAL_GID="${LOCAL_GID:-$(id -g)}"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
find app bootstrap config database public resources routes tests docker scripts -type d -exec chmod 755 {} +
find app bootstrap config database public resources routes tests docker -type f -exec chmod 644 {} +
chmod 600 .env
chmod +x artisan scripts/*.sh
chmod -R u+rwX storage bootstrap/cache
chmod -R a+rX public

docker compose build app
docker compose run --rm --no-deps app composer install --no-interaction --prefer-dist

if [ -f package-lock.json ]; then
  docker compose run --rm --no-deps node npm ci
else
  docker compose run --rm --no-deps node npm install
fi
docker compose run --rm --no-deps node npm run build
chmod -R a+rX public

docker compose up -d --wait mysql redis mailpit
if ! grep -Eq '^APP_KEY=.+$' .env; then
  docker compose run --rm --no-deps app php artisan key:generate --force
fi
docker compose run --rm --no-deps app php artisan migrate --seed --force
docker compose up -d app web queue

echo 'FORM を起動しました: http://localhost:8080'
echo '開発用メール受信箱: http://localhost:8025'
