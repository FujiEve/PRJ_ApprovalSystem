## 動作確認

```sh
docker compose up --build -d
```

- バックエンドAPI: http://localhost:8080/api/health
- フロントエンド経由のAPI: http://localhost:3000/api/health

## バックエンドテスト

バックエンドのLaravelプロジェクトに移動してテストを実行します。

```sh
cd backend

# Unit / Feature をすべて実行
php artisan test

# スイートごとに実行
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Feature
```

## フロントエンドテスト

フロントエンドのディレクトリでテストを実行します。

```sh
cd frontend

# 全テストを1回実行
npm test

# ファイル変更を監視して実行
npm run test:watch
```

## カバレッジ確認

ブランチ（パス）カバレッジの計測には、PHPUnit 11とXdebugが必要です。Xdebugをインストール・有効化した上で、`XDEBUG_MODE=coverage` を指定します。

```sh
cd backend

# テキストで行・パスカバレッジを表示
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text --path-coverage

# HTMLレポートを生成（backend/coverage/index.html）
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage --path-coverage
```

この環境にXdebugなどのカバレッジドライバーがない場合、テストは実行できますがカバレッジ率は出力されません。`--path-coverage` はXdebugを使って分岐経路を計測します。