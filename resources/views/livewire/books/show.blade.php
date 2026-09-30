<div class="mx-auto w-full max-w-3xl space-y-8">
    <section class="flex flex-col gap-6 sm:flex-row">
        <x-book-cover :url="$book->thumbnail_url" :title="$book->title" class="h-48 w-32 shrink-0" />

        <div class="min-w-0 flex-1 space-y-3">
            <flux:heading size="xl" level="1">{{ $book->title }}</flux:heading>

            <flux:text>
                {{ $book->authors ?? '著者不明' }}
                @if ($book->publisher)
                    / {{ $book->publisher }}
                @endif
                @if ($book->published_date)
                    / {{ $book->published_date }}
                @endif
                @if ($book->page_count)
                    / {{ $book->page_count }} ページ
                @endif
            </flux:text>

            <div class="flex items-center gap-3 text-sm text-zinc-500">
                <span>{{ $this->stats['readers'] }} 人が本棚に登録</span>
                @if ($this->stats['average_rating'])
                    <span class="inline-flex items-center gap-1">
                        <flux:icon.star variant="solid" class="size-4 text-amber-500" />
                        {{ $this->stats['average_rating'] }}
                    </span>
                @endif
            </div>

            @auth
                <div class="flex flex-wrap gap-2">
                    @if ($this->myRecord)
                        <flux:badge :color="$this->myRecord->status->color()" icon="check">本棚: {{ $this->myRecord->status->label() }}</flux:badge>
                    @else
                        <flux:button size="sm" icon="plus" wire:click="addToShelf">本棚に追加</flux:button>
                    @endif

                    <flux:button size="sm" variant="primary" icon="pencil-square" :href="route('posts.create', $book)" wire:navigate>
                        感想を書く
                    </flux:button>
                </div>
            @endauth

            @if ($book->description)
                <p class="text-sm leading-relaxed whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $book->description }}</p>
            @endif
        </div>
    </section>

    <section class="space-y-4">
        <flux:heading size="lg" level="2">みんなの感想</flux:heading>

        @forelse ($posts as $post)
            <x-post-card :post="$post" :show-book="false" wire:key="post-{{ $post->id }}" />
        @empty
            <flux:text>まだ感想はありません。</flux:text>
        @endforelse

        {{ $posts->links() }}
    </section>
</div>
