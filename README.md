# FORM — パーソナルトレーニング予約

Laravel 12・Vue 3・TypeScript・Inertia.js・Tailwind CSSで作成した、日本語の予約アプリです。トレーナーの空き状況を14日間のカレンダーで確認し、予約・確認メールの送信・予約管理ができます。

## 起動

必要なものは Docker Engine または Docker Desktop、Docker Compose v2、GNU Make です。ホスト側へのPHP・Composer・Node.jsのインストールは不要です。

```sh
make setup
```

初回はPHPイメージのビルドと依存関係の取得に数分かかります。スクリプトは `.env.example` から `.env` を作成し、Composer/npmの依存関係、画面のビルド、DBマイグレーション、デモデータの投入、Web・キューワーカーの起動を行います。既存の `.env` と `APP_KEY` は保持します。

| 項目 | URL |
| --- | --- |
| アプリ | http://localhost:8080 |
| 開発用メール受信箱（Mailpit） | http://localhost:8025 |

MakefileはホストユーザーのUID/GIDを自動で設定します。LinuxでホストユーザーのUID/GIDが1000以外の場合に、`docker compose` を直接実行する際は次を設定してください。

```sh
export LOCAL_UID="$(id -u)"
export LOCAL_GID="$(id -g)"
```

ポートを変更する場合は `.env` に `APP_PORT=8081`、`MAILPIT_PORT=8026` などを追加し、`APP_URL` も変更してください。Web・Mailpitはホストのループバックアドレスに公開されます。

## デモアカウント

| 権限 | メールアドレス | パスワード |
| --- | --- | --- |
| 管理者 | `admin@example.com` | `Password123!` |
| クライアント | `client@example.com` | `Password123!` |

これらはローカル開発用の初期データです。自分のアカウントを会員登録することもできます。

## 利用できる機能

- トレーナー指定／指名なしでの空き状況表示と予約
- 30分刻みの開始時刻、60分・8,800円のパーソナルトレーニング
- 予約確認画面・マイ予約一覧・開始前の予約キャンセル
- 予約保存後の確認メールのキュー送信
- 管理者の予約一覧・日付／状態フィルター・集計
- トレーナーの登録／編集、曜日別勤務時間の編集、休業日の追加／削除
- ログイン・会員登録、クライアント／管理者の権限分離

予約は本日から90日先までです。月曜と登録された休業日は休業扱いです。初期営業時間は09:00〜19:00、最終予約開始時刻は18:00です。日時は日本時間（Asia/Tokyo）で表示し、DBにはUTCで保存します。セッションの時間・料金・予約可能期間は `config/booking.php` で設定します。

## メール確認

ローカル環境では実在するメールアドレスへ送信せず、Mailpitが受信します。予約後に http://localhost:8025 を開くと、宛先・本文・日時を確認できます。メールの宛先はログイン中のユーザーの登録メールアドレスです。

キューワーカーの状態と送信エラーは次で確認できます。

```sh
docker compose logs --tail=100 queue
docker compose exec app php artisan queue:failed
```

メール設定を変更した後はワーカーを再起動してください。

```sh
docker compose restart queue
```

実際のSMTPへ接続する場合は `.env` の `MAIL_HOST`・`MAIL_PORT`・`MAIL_USERNAME`・`MAIL_PASSWORD`・`MAIL_SCHEME`・`MAIL_FROM_ADDRESS` を利用するSMTPサーバーに合わせて設定します。

## テストとビルド

```sh
make test
```

このスクリプトはバックエンドのテスト、TypeScriptの型チェック、フロントエンドの本番ビルドを実行します。PHPのテストは `.env` の開発用DBを使用せず、`phpunit.xml` で指定したテスト用DBを使います。

個別に実行する場合:

```sh
docker compose run --rm --no-deps app php artisan test
docker compose run --rm --no-deps node npm run typecheck
docker compose run --rm --no-deps node npm run build
```

ブラウザによるE2Eテストの設定・シナリオは `playwright.config.ts` と `tests/e2e/` にあります。ホストにNode.jsを用意し、起動済みアプリを対象に実行します。

```sh
npm ci
npx playwright install chromium
npm run test:e2e
```

E2Eは起動中の開発DBに、`e2e-`で始まる会員と受付停止状態のテスト用トレーナーを作成します。デモアカウントを投入した開発環境で実行してください。既存のChromiumを使う場合は `PLAYWRIGHT_CHROMIUM_EXECUTABLE=/path/to/chromium` を指定できます。

テストの対象は、予約の入力検証、重複・営業時間・勤務時間の判定、本人／管理者の操作権限、予約確認メール、予約操作です。二重予約は入力検証に加え、トランザクション内でトレーナー行をロックし、空き状況を再確認して防止します。

## 動作確認結果

- PHPUnit: 40件・235アサーション成功
- TypeScriptの型チェックとViteの本番ビルド成功
- Playwright: 6件成功（390pxの画面、予約・取消、権限、管理編集、ログイン後の選択復元）
- MySQL 8.4上の競合する同時予約で、1件のみ確定することを確認
- Redisキュー経由の確認メールがMailpitに届くことを確認

## 開発時の操作

`make` または `make help` でコマンド一覧を表示します。初回は `make setup` でビルドから起動まで実行できます（内部で既存の `scripts/setup.sh` を使用）。Makeがない場合は従来どおりスクリプトを直接実行できます。

```sh
# セットアップ後の再ビルドと起動（順番に実行）
make build && make up

# 状態確認・停止・ログ確認
make ps
make down
make logs SERVICE=queue

# フロントエンド変更の反映
make build-assets

# テスト・型チェック・PHPコードスタイル確認
make test
make typecheck
make lint

# DB更新・Laravelキャッシュ削除・任意のArtisanコマンド
make migrate
make cache-clear
make artisan ARGS='queue:failed'
```

`make restart` は既存コンテナの再起動です。イメージやCompose設定の変更後は `make up` でコンテナを再作成してください。`make format` でPHPコードを整形、`make shell` でappコンテナのシェルを開けます。`make seed` は起動中の開発環境にデモデータを投入します。

Docker Composeを直接使用する場合:

```sh
# 停止（DBデータは保持）
docker compose down

# 再起動
docker compose up -d

# アプリケーションログ
docker compose logs -f app queue web

# フロントエンド変更後の反映
docker compose run --rm --no-deps node npm run build

# マイグレーション
docker compose exec app php artisan migrate
```

初期化してデモデータからやり直す場合のみ、次のコマンドを使用します。予約・会員情報を含むDBデータが削除されます。

```sh
docker compose exec app php artisan migrate:fresh --seed
```

## 構成

```text
app/Http/Controllers/   HTTP処理・管理画面
app/Http/Requests/      入力バリデーション
app/Models/            DBモデル
app/Policies/          本人・管理者の操作権限
app/Services/          予約確定・空き状況計算
app/Mail/              キュー送信用メール
config/booking.php     予約条件
resources/js/          Vue・TypeScript・画面と部品
resources/css/         Tailwind CSSと共通スタイル
resources/views/       HTMLとメールテンプレート
database/              マイグレーション・Factory・Seeder
tests/                 PHPテスト・E2Eテスト
docker/                PHP・Nginx設定
scripts/               初回起動・テスト用スクリプト
compose.yaml           サービス構成
```

Docker構成はローカル開発用です。実運用時にはHTTPS・実際のSMTP・定期バックアップ・`APP_DEBUG=false`・環境ごとの認証情報を設定し、デモアカウントを削除してください。
