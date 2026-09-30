<?php

namespace App\Livewire\Shelf;

use App\Enums\ReadingStatus;
use App\Models\ReadingRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('本棚')]
class Index extends Component
{
    use WithPagination;

    /** 絞り込むステータス。空文字はすべて。 */
    #[Url(except: '')]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, ReadingRecord>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return Auth::user()->readingRecords()
            ->with('book')
            ->when(
                ReadingStatus::tryFrom($this->status),
                fn ($query, ReadingStatus $status) => $query->where('status', $status),
            )
            ->latest('updated_at')
            ->paginate(20);
    }

    /**
     * ステータスごとの登録数。
     *
     * @return Collection<string, int>
     */
    #[Computed]
    public function counts(): Collection
    {
        return Auth::user()->readingRecords()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);
    }

    public function changeStatus(int $recordId, string $status): void
    {
        $record = $this->findRecord($recordId);
        $this->authorize('update', $record);

        $record->changeStatus(ReadingStatus::tryFrom($status) ?? abort(422));

        unset($this->records, $this->counts);
    }

    public function rate(int $recordId, int $rating): void
    {
        $record = $this->findRecord($recordId);
        $this->authorize('update', $record);

        abort_unless($rating >= 0 && $rating <= 5, 422);

        // 同じ星をもう一度押したら評価を取り消す
        $record->update(['rating' => $rating === 0 || $record->rating === $rating ? null : $rating]);

        unset($this->records);
    }

    public function remove(int $recordId): void
    {
        $record = $this->findRecord($recordId);
        $this->authorize('delete', $record);

        $record->delete();

        unset($this->records, $this->counts);
    }

    /**
     * 他人のレコード ID を指定された場合も取得し、Policy で拒否する。
     */
    private function findRecord(int $recordId): ReadingRecord
    {
        return ReadingRecord::findOrFail($recordId);
    }

    public function render(): View
    {
        return view('livewire.shelf.index');
    }
}
