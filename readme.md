## 動作確認

```sh
docker compose up --build -d
```

- バックエンドAPI: http://localhost:8080/api/health
- フロントエンド: http://localhost:3000
- フロントエンド経由のAPI: http://localhost:3000/api/health

## バックエンドテスト

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

## Next.jsのローカル開発

Docker Composeのfrontendサービスは`frontend/`をbind mountするため、ホストとコンテナでNext.jsの生成物`.next`を共有します。Dockerのfrontendを起動したまま、ホスト側で別の`next dev`や`next build`を同時実行しないでください。

manifestエラーなどが発生した場合は、frontendを停止して共有キャッシュを削除してから再起動します。

```sh
docker compose stop frontend
rm -rf frontend/.next
docker compose start frontend
```