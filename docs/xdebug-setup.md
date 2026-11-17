# Laravel / Docker / VS Code のXdebug設定

## 調査した環境

- PHP 8.4.27（`php:8.4-fpm-alpine`）、Laravel 12.72.0
- Docker Compose v5.7.0。Nginx → appのPHP-FPM。Sailは起動に使用していません。
- PHP設定：`docker/php/php.ini`、ビルド：`docker/php/Dockerfile`
- リポジトリに既存の`.vscode`設定はありませんでした。ホストにはPHP Debug 1.40.2がインストール済みでした。
- Xdebug 3.5.3をデバッグ専用イメージに追加。通常の最終ビルドステージ`production`にはインストールしません。

## 起動と終了

初回のアプリ準備は`make setup`。その後、プロジェクトルートで実行します。

```sh
make debug-up
make debug-info
```

`debug-up`は`compose.yaml`に`compose.debug.yaml`を重ね、appだけを`form-training-php:debug`に切り替えます。queueは通常イメージのままです。切り替え後にNginxをreloadし、appの新しいIPを解決します。

```sh
# 元のXdebugなし環境へ戻す
make debug-off
```

通常の`make up`も通常イメージに戻すため、デバッグ時は`make debug-up`を使ってください。Xcodeのライセンス未同意でmakeが実行できない場合、ライセンス設定を行うか、インストール済みの`/Library/Developer/CommandLineTools/usr/bin/make`を使用できます。

本番ビルドは通常のDockerfileの最終ステージ、または明示的な`--target production`を使い、`compose.debug.yaml`を適用しないでください。`APP_ENV=production`だけでは、誤ってデバッグイメージを使った場合のXdebugは無効になりません。本番には通常イメージを使います。既存Compose自体もローカル開発用です。

## VS Codeの開始手順

1. このプロジェクトのルートフォルダーをVS Codeで開きます。
2. 拡張機能「PHP Debug」（ID：`xdebug.php-debug`）をインストールします。
3. 「実行とデバッグ」で`Listen for Xdebug (Docker)`を選択し、F5で待ち受けます。
4. 実行されるPHPコード行の左側をクリックしてブレークポイントを置きます。
5. トリガー付きのリクエストを送ります。

`.vscode/launch.json`はホストの9003番で待ち受け、コンテナ内の`/var/www/html`を`${workspaceFolder}`に対応づけます。XdebugからVS Codeへ接続するため、Dockerの`ports`に9003を追加する必要はありません。`host.docker.internal:host-gateway`でホストへ接続します。VS CodeをMac/Windows/Linuxホスト側で実行する構成です。Remote SSHやDev Containers内のVS Codeには接続先の調整が必要です。

設定は`docker/php/xdebug.ini`です。

```ini
xdebug.mode=debug
xdebug.start_with_request=trigger
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
```

通常のアクセスでは停止せず、`XDEBUG_TRIGGER=1`があるリクエストだけデバッグを開始します。

## ControllerとAPIのデバッグ

公開画面の例：`app/Http/Controllers/ReservationController.php`の`create()`内にブレークポイントを置き、以下を開きます。

```text
http://localhost:8080/?XDEBUG_TRIGGER=1
```

管理者の予約一覧APIでは、`Admin/ReservationController.php`の`data()`内に置きます。管理者でログイン済みのブラウザ、またはログインセッションCookieを取得済みのPostmanから送信します。

```http
GET http://localhost:8080/api/admin/reservations?XDEBUG_TRIGGER=1
Accept: application/json
```

PostmanのParamsに`XDEBUG_TRIGGER=1`を追加すれば使用できます。通常のHTTPヘッダーとして追加するのではありません。未ログインなら401、一般会員なら403となり、Controllerまで到達しません。ブラウザのログインCookieはPostmanには自動共有されません。

## ReservationService::create()をデバッグする具体例

**予約一覧のGETでは、このメソッドは呼ばれません。予約確定のPOSTが必要です。**

1. `make debug-up`を実行し、VS CodeでF5を押します。
2. `app/Services/ReservationService.php`を開き、`create()`の`return DB::transaction(...)`と、クロージャ内の`$start = ...`の行にブレークポイントを置きます。
3. ブラウザでログインし、予約画面を開きます。DevToolsのConsoleで次を実行し、後続の予約POSTにもトリガーを送ります。

   ```js
   document.cookie = 'XDEBUG_TRIGGER=1; path=/; SameSite=Lax';
   ```

4. 空いている日時を選び、予約確認画面から「予約を確定する」を押します。
5. 停止したらVARIABLES（変数）で`$user`、`$data`を展開し、会員情報・予約日・時刻・トレーナーIDを確認します。
6. F5でクロージャ内のブレークポイントまで進めます。F10（Step Over）で`$start`の代入を実行し、UTCへの変換結果を確認します。
7. `$this->availability->availableTrainers(...)`の行まで進め、F11（Step Into）で`AvailabilityService`へ入ります。Shift+F11で呼び出し元へ戻れます。Macではキー設定によってFnキーが必要です。
8. F5で続行すると予約が保存されます。実験用の予約はアプリからキャンセルしてください。
9. 終了後はConsoleでトリガーCookieを削除し、VS Codeの停止ボタンで待ち受けを終了します。

   ```js
   document.cookie = 'XDEBUG_TRIGGER=; Max-Age=0; path=/';
   ```

Postmanで予約作成を確認する場合は、ログイン済みCookieとCSRFヘッダーを使い、`POST /reservations?XDEBUG_TRIGGER=1`に`date`、`time`、任意の`trainer_id`・`notes`を送ります。空き状況に合う未来日時が必要です。このエンドポイントは既存のInertiaフォーム用で、成功時はリダイレクトを返します。

実データを作らずServiceを確認するなら、テスト用SQLiteのメモリDBを使う既存テストをデバッグできます。

```sh
docker compose exec -e XDEBUG_TRIGGER=1 app php vendor/bin/phpunit --filter test_a_client_can_book_and_the_confirmation_mail_is_queued_after_commit
```

このテストはテスト用DBとMailのFakeを使用し、開発DBへの予約追加や実メール送信を行いません。

## 接続エラーの確認

| 症状 | 確認・対処 |
| --- | --- |
| Xdebugが見つからない | `make debug-info`、`docker compose exec app php -v`を確認。`make debug-up`で切り替える |
| 接続できない | F5の待ち受け、9003番、ファイアウォールを確認。`docker compose exec app cat /tmp/xdebug.log`で接続先を見る |
| ブレークポイントが灰色・停止しない | プロジェクトルートを開き、pathMappingsと実際のコンテナパスを確認。コメントやメソッド宣言ではなく実行行に置く |
| 一覧では止まるがServiceで止まらない | `ReservationService::create()`は予約確定のPOSTで呼ばれる。GETだけでは呼ばれない |
| 401 / 403 / 419 / 422 | 認証・権限・CSRF・入力検証を確認。Serviceより前に処理が終了する場合がある |
| 504やタイムアウト | 停止中はHTTPも待機する。既存Nginxのタイムアウトを超える前に続行する。長時間の調査はCLIテストで行う |
| 9003が使用中 | 他のデバッガの待ち受けを停止。複数のVS Codeウィンドウで同じポートを使用しない |
| 通常起動に戻った | `make up`は通常構成。再度`make debug-up`を実行 |

デバッグ用ログはコンテナの`/tmp/xdebug.log`に保存されます。APIのトークンや個人情報を含み得るため共有前に内容を確認してください。

## 参考

- [Xdebugの対応バージョン](https://xdebug.org/docs/compat)
- [Xdebugのインストール](https://xdebug.org/docs/install)
- [Xdebugのステップデバッグ](https://xdebug.org/docs/step_debug)
- [VS Code PHP Debug公式](https://github.com/xdebug/vscode-php-debug)

## 実施した動作確認

- appでPHP 8.4.27 / Xdebug 3.5.3 / `xdebug.mode=debug`を確認。
- ホストのPHP Debug拡張1.40.2のアダプターを直接起動し、9003番で接続を検証。
- トリガー付きHTTP GETで、PHP-FPMの`ReservationController::create()`に設定したブレークポイントで停止。
- テスト用DBで予約作成テストを実行し、`ReservationService::create()`内で停止。
- `$data`を展開し、テストの予約日`2030-01-09`を取得できることを確認。
- Step Overで次の行へ進み、Step Intoで呼び出し先のLaravelヘルパーに入れることを確認。
- **未確認：VS CodeのGUIでのF5操作、変数パネルの実表示。** 同じPHP Debug拡張の通信・デバッグ機能は確認済みですが、GUI操作の検証とは区別しています。
- Xdebug有効のappでPHPUnit全46件・287アサーション成功。
- `make debug-off`で通常イメージへ戻し、Xdebugが未搭載であることを確認。`make debug-up`で再度有効化済み。
