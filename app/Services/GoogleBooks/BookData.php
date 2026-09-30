<?php

namespace App\Services\GoogleBooks;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Google Books API の volume を、アプリで扱いやすい形に詰め替えた DTO。
 */
final readonly class BookData
{
    public function __construct(
        public string $googleBooksId,
        public string $title,
        public ?string $authors = null,
        public ?string $publisher = null,
        public ?string $publishedDate = null,
        public ?string $description = null,
        public ?string $isbn13 = null,
        public ?string $thumbnailUrl = null,
        public ?int $pageCount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $item  Google Books API の volume リソース
     */
    public static function fromApi(array $item): self
    {
        $info = $item['volumeInfo'] ?? [];

        $isbn13 = collect($info['industryIdentifiers'] ?? [])
            ->firstWhere('type', 'ISBN_13')['identifier'] ?? null;

        $thumbnail = Arr::get($info, 'imageLinks.thumbnail') ?? Arr::get($info, 'imageLinks.smallThumbnail');

        return new self(
            googleBooksId: $item['id'],
            title: Str::limit($info['title'] ?? '（タイトル不明）', 250, ''),
            authors: isset($info['authors']) ? Str::limit(implode(', ', $info['authors']), 250, '') : null,
            publisher: isset($info['publisher']) ? Str::limit($info['publisher'], 250, '') : null,
            publishedDate: isset($info['publishedDate']) ? Str::limit($info['publishedDate'], 10, '') : null,
            description: isset($info['description']) ? strip_tags($info['description']) : null,
            isbn13: $isbn13,
            // API は http の URL を返すため、混在コンテンツにならないよう https に揃える
            thumbnailUrl: $thumbnail ? Str::replaceFirst('http://', 'https://', $thumbnail) : null,
            pageCount: isset($info['pageCount']) ? (int) $info['pageCount'] : null,
        );
    }

    /**
     * books テーブルに保存する属性。
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'isbn_13' => $this->isbn13,
            'title' => $this->title,
            'authors' => $this->authors,
            'publisher' => $this->publisher,
            'published_date' => $this->publishedDate,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnailUrl,
            'page_count' => $this->pageCount,
        ];
    }
}
