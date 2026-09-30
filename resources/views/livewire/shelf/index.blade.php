<div class="mx-auto w-full max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">本棚</flux:heading>
        <flux:button size="sm" icon="magnifying-glass" :href="route('books.search')" wire:navigate>本を探す</flux:button>
    </div>

    <flux:radio.group wire:model.live="status" variant="segmented" size="sm">
        <flux:radio value="" label="すべて ({{ $this->counts->sum() }})" />
        @foreach (\App\Enums\ReadingStatus::cases() as $option)
            <flux:radio :value="$option->value" label="{{ $option->label() }} ({{ $this->counts->get($option->value, 0) }})" />
        @endforeach
    </flux:radio.group>

    @if ($this->records->isEmpty())
        <flux:text>
            本棚に本がありません。<flux:link :href="route('books.search')" wire:navigate>本を探して</flux:link>登録しましょう。
        </flux:text>
    @endif

    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
        @foreach ($this->records as $record)
            <li wire:key="record-{{ $record->id }}" class="flex gap-4 py-4">
                <a href="{{ route('books.show', $record->book) }}" wire:navigate class="shrink-0">
                    <x-book-cover :url="$record->book->thumbnail_url" :title="$record->book->title" class="h-24 w-16" />
                </a>

                <div class="min-w-0 flex-1 space-y-2">
                    <a href="{{ route('books.show', $record->book) }}" wire:navigate class="block font-semibold hover:underline">
                        {{ $record->book->title }}
                    </a>
                    <p class="text-sm text-zinc-500">{{ $record->book->authors ?? '著者不明' }}</p>

                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <flux:select size="sm" class="max-w-40" wire:change="changeStatus({{ $record->id }}, $event.target.value)" aria-label="ステータス">
                            @foreach (\App\Enums\ReadingStatus::cases() as $option)
                                <flux:select.option :value="$option->value" :selected="$record->status === $option">{{ $option->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <span class="inline-flex" aria-label="評価">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button" wire:click="rate({{ $record->id }}, {{ $i }})" class="text-amber-500" title="★{{ $i }}">
                                    <flux:icon.star :variant="$record->rating && $i <= $record->rating ? 'solid' : 'outline'" class="size-5" />
                                </button>
                            @endfor
                        </span>

                        @if ($record->started_on)
                            <span class="text-zinc-500">
                                {{ $record->started_on->format('Y/m/d') }} 〜 {{ $record->finished_on?->format('Y/m/d') }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 flex-col items-end gap-2">
                    <flux:button size="sm" icon="pencil-square" :href="route('posts.create', $record->book)" wire:navigate>感想</flux:button>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="remove({{ $record->id }})" wire:confirm="本棚から削除しますか？（書いた感想は残ります）">
                        削除
                    </flux:button>
                </div>
            </li>
        @endforeach
    </ul>

    {{ $this->records->links() }}
</div>
