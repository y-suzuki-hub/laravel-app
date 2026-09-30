<?php

namespace App\Livewire\Books;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\ReadingRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public Book $book;

    #[Computed]
    public function myRecord(): ?ReadingRecord
    {
        return Auth::check()
            ? $this->book->readingRecords()->whereBelongsTo(Auth::user())->first()
            : null;
    }

    /**
     * @return array{readers: int, average_rating: float|null}
     */
    #[Computed]
    public function stats(): array
    {
        $stats = $this->book->readingRecords()
            ->selectRaw('count(*) as readers, avg(rating) as average_rating')
            ->first();

        return [
            'readers' => (int) $stats->readers,
            'average_rating' => $stats->average_rating !== null ? round((float) $stats->average_rating, 1) : null,
        ];
    }

    public function addToShelf(): void
    {
        abort_unless(Auth::check(), 403);

        $record = Auth::user()->readingRecords()->firstOrNew(['book_id' => $this->book->id]);

        if (! $record->exists) {
            $record->changeStatus(ReadingStatus::Want);
        }

        unset($this->myRecord, $this->stats);
    }

    public function render(): View
    {
        return view('livewire.books.show', [
            'posts' => $this->book->posts()
                ->with(['user', 'book', 'tags'])
                ->latest()
                ->paginate(10),
        ])->title($this->book->title);
    }
}
