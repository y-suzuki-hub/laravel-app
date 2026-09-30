<?php

namespace App\Livewire\Books;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\ReadingRecord;
use App\Services\GoogleBooks\BookData;
use App\Services\GoogleBooks\GoogleBooksClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('本を探す')]
class Search extends Component
{
    #[Url(as: 'q', except: '')]
    public string $keyword = '';

    /**
     * 検索結果。API の呼び出しに失敗した場合は null。
     *
     * @return Collection<int, BookData>|null
     */
    #[Computed]
    public function results(): ?Collection
    {
        if (mb_strlen(trim($this->keyword)) < 2) {
            return collect();
        }

        try {
            return app(GoogleBooksClient::class)->search($this->keyword);
        } catch (RequestException|ConnectionException $e) {
            Log::warning('Google Books search failed', ['keyword' => $this->keyword, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * 検索結果のうち、ログインユーザーが本棚に登録済みの本（google_books_id => ステータス）。
     *
     * @return Collection<string, ReadingStatus>
     */
    #[Computed]
    public function shelved(): Collection
    {
        $ids = $this->results?->pluck('googleBooksId');

        if ($ids === null || $ids->isEmpty()) {
            return collect();
        }

        return ReadingRecord::query()
            ->whereBelongsTo(Auth::user())
            ->whereHas('book', fn ($query) => $query->whereIn('google_books_id', $ids))
            ->with('book:id,google_books_id')
            ->get()
            ->mapWithKeys(fn (ReadingRecord $record) => [$record->book->google_books_id => $record->status]);
    }

    public function addToShelf(string $googleBooksId, string $status = 'want'): void
    {
        $status = ReadingStatus::tryFrom($status) ?? abort(422);

        $data = app(GoogleBooksClient::class)->find($googleBooksId) ?? abort(404);

        $book = Book::fromBookData($data);

        $record = Auth::user()->readingRecords()->firstOrNew(['book_id' => $book->id]);

        if (! $record->exists) {
            $record->changeStatus($status);
        }

        unset($this->shelved);
    }

    public function render(): View
    {
        return view('livewire.books.search');
    }
}
