# ReadLog

Laravel + Livewire で作る読書記録 SNS（学習・ポートフォリオ用）。

- 設計書: [docs/design.md](docs/design.md)

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

http://localhost:8000 を開く。

## テスト・コード整形

```bash
./vendor/bin/pest
./vendor/bin/pint
```
