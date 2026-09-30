<?php

namespace App\Services\GoogleBooks;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleBooksClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://www.googleapis.com/books/v1',
        private readonly int $cacheSeconds = 600,
    ) {}

    /**
     * キーワードで書籍を検索する。同じキーワードの結果は一定時間キャッシュする。
     *
     * @return Collection<int, BookData>
     *
     * @throws RequestException
     */
    public function search(string $keyword, int $limit = 20): Collection
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return collect();
        }

        $items = Cache::remember(
            'google-books:search:'.md5($keyword.'|'.$limit),
            $this->cacheSeconds,
            fn () => $this->request()
                ->get('/volumes', array_filter([
                    'q' => $keyword,
                    'maxResults' => $limit,
                    'printType' => 'books',
                    'key' => $this->apiKey,
                ]))
                ->throw()
                ->json('items', []),
        );

        return collect($items)->map(fn (array $item) => BookData::fromApi($item));
    }

    /**
     * Google Books の ID で書籍を 1 件取得する。見つからなければ null。
     *
     * @throws RequestException
     */
    public function find(string $googleBooksId): ?BookData
    {
        $item = Cache::remember(
            'google-books:volume:'.$googleBooksId,
            $this->cacheSeconds,
            function () use ($googleBooksId) {
                $response = $this->request()
                    ->get('/volumes/'.rawurlencode($googleBooksId), array_filter(['key' => $this->apiKey]));

                if ($response->notFound()) {
                    return false;
                }

                return $response->throw()->json();
            },
        );

        return $item ? BookData::fromApi($item) : null;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(5)
            ->retry(2, 200, throw: false);
    }
}
