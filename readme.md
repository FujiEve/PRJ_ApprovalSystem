## 動作確認用

```
docker compose up --build -d
```

- フロントエンド: http://localhost:3000
- バックエンドAPI: http://localhost:8080/api/health

PostgreSQLのデータファイルは`database/postgres/`へ保存されます。Docker ComposeのDBコンテナを停止しても保持され、Dockerの名前付きボリュームではなくホスト側のフォルダを利用します。既存の名前付きボリュームからデータを移行済みです。旧ボリュームは移行確認用に保持しています。

`database/postgres/`はGit管理対象外です。ファイル同期サービスを利用する場合、PostgreSQL稼働中にこのフォルダを別端末へ同期・複製しないでください。整合性を保つにはDBを停止してから同期してください。また、複数端末で同じDBフォルダを同時に起動しないでください。pread