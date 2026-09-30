<?php

namespace App\Models;

use App\Enums\ReadingStatus;
use Database\Factories\ReadingRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingRecord extends Model
{
    /** @use HasFactory<ReadingRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'book_id',
        'status',
        'rating',
        'current_page',
        'started_on',
        'finished_on',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReadingStatus::class,
            'rating' => 'integer',
            'current_page' => 'integer',
            'started_on' => 'date',
            'finished_on' => 'date',
        ];
    }

    /**
     * ステータスを変更し、読み始め・読み終わりの日付を記録する。
     */
    public function changeStatus(ReadingStatus $status): void
    {
        $this->status = $status;

        if ($status === ReadingStatus::Reading) {
            $this->started_on ??= today();
        }

        if ($status === ReadingStatus::Finished) {
            $this->started_on ??= today();
            $this->finished_on ??= today();
        } else {
            $this->finished_on = null;
        }

        $this->save();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
