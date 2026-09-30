<div class="mx-auto w-full max-w-3xl space-y-10">
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="2">読んでいる本</flux:heading>
            <flux:link :href="route('shelf.index')" wire:navigate class="text-sm">本棚を見る</flux:link>
        </div>

        @if ($this->readingNow->isEmpty())
            <flux:text>
                読んでいる本はありません。<flux:link :href="route('books.search')" wire:navigate>本を探す</flux:link>
            </flux:text>
        @else
            <ul class="grid grid-cols-3 gap-4 sm:grid-cols-6">
                @foreach ($this->readingNow as $record)
                    <li wire:key="reading-{{ $record->id }}">
                        <a href="{{ route('books.show', $record->book) }}" wire:navigate class="block space-y-1">
                            <x-book-cover :url="$record->book->thumbnail_url" :title="$record->book->title" class="aspect-[2/3] w-full" />
                            <p class="line-clamp-2 text-xs">{{ $record->book->title }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="space-y-4">
        <flux:heading size="lg" level="2">みんなの新着の感想</flux:heading>

        @forelse ($this->recentPosts as $post)
            <x-post-card :post="$post" wire:key="post-{{ $post->id }}" />
        @empty
            <flux:text>まだ感想はありません。</flux:text>
        @endforelse
    </section>
</div>
