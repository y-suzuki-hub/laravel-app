<?php

namespace App\Models;

use App\Services\GoogleBooks\BookData;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected $fillable = [
        'google_books_id',
        'isbn_13',
        'title',
        'authors',
        'publisher',
        'published_date',
        'description',
        'thumbnail_url',
        'page_count',
    ];

    /**
     * Google Books の書籍データを保存する。既に登録済みなら最新の情報で更新する。
     */
    public static function fromBookData(BookData $data): self
    {
        return self::updateOrCreate(
            ['google_books_id' => $data->googleBooksId],
            $data->toAttributes(),
        );
    }

    /**
     * @return HasMany<ReadingRecord, $this>
     */
    public function readingRecords(): HasMany
    {
        return $this->hasMany(ReadingRecord::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
