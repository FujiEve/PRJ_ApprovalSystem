## 動作確認

```sh
docker compose up --build -d
```

- バックエンドAPI: http://localhost:8080/api/health
- フロントエンド: http://localhost:3000
- フロントエンド経由のAPI: http://localhost:3000/api/health

## 添付資料

- PDF、JPEG、PNG、Word、Excelファイルをアップロードできます（1ファイル20MB以下、1稟議10ファイルまで）。
- ファイルは`backend/storage/app/private/approvals/`に保存され、公開URLではなく閲覧権限を確認するAPI経由でダウンロードします。
- 下書き・差し戻し案件の編集画面では、既存ファイルの削除と新規ファイルの追加を保存時に反映します。

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

## 添付資料

- PDF、JPEG、PNG、Word、Excelファイルをアップロードできます（1ファイル20MB以下、1稟議10ファイルまで）。
- ファイル本体は`backend/storage/app/private/approvals/`に保存され、公開URLではなく権限付きAPI経由でダウンロードします。
- 下書き・差し戻しの編集画面では、既存ファイルの削除と新規ファイル追加を保存時に反映します。

## Next.jsのローカル開発

Docker Composeのfrontendサービスは`frontend/`をbind mountするため、ホストとコンテナでNext.jsの生成物`.next`を共有します。Dockerのfrontendを起動したまま、ホスト側で別の`next dev`や`next build`を同時実行しないでください。

manifestエラーなどが発生した場合は、frontendを停止して共有キャッシュを削除してから再起動します。

```sh
docker compose stop frontend
rm -rf frontend/.next
docker compose start frontend
```