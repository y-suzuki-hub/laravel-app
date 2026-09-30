<?php

namespace App\Providers;

use App\Services\GoogleBooks\GoogleBooksClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleBooksClient::class, fn () => new GoogleBooksClient(
            apiKey: config('services.google_books.key'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 開発・テスト中は N+1（遅延読み込み）や未定義属性へのアクセスを例外にして早期に気づけるようにする
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
