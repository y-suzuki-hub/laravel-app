# ReadLog

Laravel + Livewire で作る読書記録 SNS（学習・ポートフォリオ用）。

- 設計書: [docs/design.md](docs/design.md)

## 機能（Phase 1）

- ユーザー登録・ログイン（ユーザー名・自己紹介つき）
- Google Books API による書籍検索と本棚への登録
- 本棚: ステータス（読みたい / 読んでいる / 読み終わった / 中断）、★評価、読書期間の自動記録
- 感想の投稿・編集・削除、タグ付け、ネタバレの折りたたみ表示
- 書籍ページ（みんなの感想・登録者数・平均評価）はログインなしで閲覧可能
- Policy による認可（他人の本棚・投稿は変更できない）

## 技術スタック

- Laravel 12 / PHP 8.4
- Livewire 4 + Flux（公式 Livewire スターターキット）
- Tailwind CSS
- Pest

## ローカルでの起動

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
composer run dev   # サーバー・キュー・ログ・Vite をまとめて起動
```

http://localhost:8000 を開き、`test@example.com` / `password` でログインできる（シーダーのテストユーザー）。

書籍検索は Google Books API を使う。API キーなしでも動くが、回数制限にかかりやすいため
[Google Cloud Console](https://console.cloud.google.com/) で Books API のキーを発行し、`.env` の `GOOGLE_BOOKS_API_KEY` に設定するのがおすすめ。

## テスト・コード整形

```bash
./vendor/bin/pest
./vendor/bin/pint
```
