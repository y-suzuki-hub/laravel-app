<?php

namespace App\Livewire;

use App\Enums\ReadingStatus;
use App\Models\Post;
use App\Models\ReadingRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('ホーム')]
class Dashboard extends Component
{
    /**
     * @return Collection<int, ReadingRecord>
     */
    #[Computed]
    public function readingNow(): Collection
    {
        return Auth::user()->readingRecords()
            ->where('status', ReadingStatus::Reading)
            ->with('book')
            ->latest('updated_at')
            ->limit(6)
            ->get();
    }

    /**
     * みんなの新着の感想（Phase 2 でフォロー中ユーザーのタイムラインに置き換える）。
     *
     * @return Collection<int, Post>
     */
    #[Computed]
    public function recentPosts(): Collection
    {
        return Post::query()
            ->with(['user', 'book', 'tags'])
            ->latest()
            ->limit(20)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
