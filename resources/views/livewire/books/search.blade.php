<div class="mx-auto w-full max-w-3xl space-y-6">
    <flux:heading size="xl" level="1">本を探す</flux:heading>

    <flux:input
        wire:model.live.debounce.500ms="keyword"
        icon="magnifying-glass"
        placeholder="タイトル・著者名・ISBN で検索"
        clearable
        autofocus
    />

    <div wire:loading.delay wire:target="keyword" class="text-sm text-zinc-500">検索中…</div>

    @if ($this->results === null)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="検索に失敗しました。時間をおいて再度お試しください。" />
    @elseif (mb_strlen(trim($keyword)) >= 2 && $this->results->isEmpty())
        <flux:text>「{{ $keyword }}」に一致する本は見つかりませんでした。</flux:text>
    @endif

    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700" wire:loading.class="opacity-50" wire:target="keyword">
        @foreach ($this->results ?? [] as $book)
            <li wire:key="result-{{ $book->googleBooksId }}" class="flex gap-4 py-4">
                <x-book-cover :url="$book->thumbnailUrl" :title="$book->title" class="h-24 w-16 shrink-0" />

                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $book->title }}</p>
                    <p class="text-sm text-zinc-500">
                        {{ $book->authors ?? '著者不明' }}
                        @if ($book->publishedDate)
                            · {{ $book->publishedDate }}
                        @endif
                    </p>
                    @if ($book->description)
                        <p class="mt-1 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $book->description }}</p>
                    @endif
                </div>

                <div class="shrink-0 self-center">
                    @if ($status = $this->shelved->get($book->googleBooksId))
                        <flux:badge :color="$status->color()" icon="check">{{ $status->label() }}</flux:badge>
                    @else
                        <flux:dropdown>
                            <flux:button size="sm" icon="plus" icon:trailing="chevron-down">本棚に追加</flux:button>

                            <flux:menu>
                                @foreach (\App\Enums\ReadingStatus::cases() as $option)
                                    <flux:menu.item wire:click="addToShelf('{{ $book->googleBooksId }}', '{{ $option->value }}')">
                                        {{ $option->label() }}
                                    </flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</div>
