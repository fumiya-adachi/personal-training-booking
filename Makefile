.DEFAULT_GOAL := help

# コンテナ内で生成するファイルの所有者をホストユーザーに揃える。
export LOCAL_UID ?= $(shell id -u)
export LOCAL_GID ?= $(shell id -g)

.PHONY: help setup build build-image build-assets up down restart ps logs test test-php typecheck lint format migrate seed cache-clear shell artisan

help: ## コマンド一覧を表示
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z0-9_-]+:.*## / {printf "  %-16s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

setup: ## 初回セットアップ（ビルド・依存関係の取得・DB準備・起動）
	./scripts/setup.sh

build: ## PHPイメージとフロントエンドをビルド（setup実行後）
	$(MAKE) build-image
	$(MAKE) build-assets

build-image: ## PHPイメージをビルド
	docker compose build app

build-assets: ## 型チェックとフロントエンドの本番ビルド（setup実行後）
	docker compose run --rm --no-deps node npm run build
	chmod -R a+rX public

up: ## セットアップ済みサービスを起動（ビルド後のコンテナ再作成にも使用）
	docker compose up -d --wait

down: ## サービスを停止・削除（DBのボリュームは保持）
	docker compose down

restart: ## 起動中のサービスを再起動
	docker compose restart

ps: ## サービスの状態を表示
	docker compose ps

logs: ## ログを追跡（例: make logs SERVICE=queue）
	docker compose logs -f --tail=100 $(SERVICE)

test: ## PHPテスト・型チェック・フロントエンドビルド（setup実行後）
	./scripts/test.sh

test-php: ## PHPテストのみ実行（setup実行後）
	docker compose run --rm --no-deps app php artisan test

typecheck: ## TypeScriptの型チェック（setup実行後）
	docker compose run --rm --no-deps node npm run typecheck

lint: ## PHPのコードスタイルを確認（setup実行後）
	docker compose run --rm --no-deps app vendor/bin/pint --test

format: ## PHPのコードスタイルを自動修正（setup実行後）
	docker compose run --rm --no-deps app vendor/bin/pint

migrate: ## マイグレーションを実行（起動中の環境）
	docker compose exec app php artisan migrate

seed: ## デモデータを投入（起動中の環境）
	docker compose exec app php artisan db:seed

cache-clear: ## Laravelのキャッシュをクリア（起動中の環境）
	docker compose exec app php artisan optimize:clear

shell: ## appコンテナのシェルを開く
	docker compose exec app sh

artisan: ## Artisanを実行（例: make artisan ARGS='queue:failed'）
	docker compose exec app php artisan $(ARGS)
