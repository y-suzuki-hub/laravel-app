@props(['post', 'showBook' => true])

<article {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 p-4 dark:border-zinc-700']) }}>
    <div class="flex gap-4">
        @if ($showBook)
            <a href="{{ route('books.show', $post->book) }}" wire:navigate class="shrink-0">
                <x-book-cover :url="$post->book->thumbnail_url" :title="$post->book->title" class="h-20 w-14" />
            </a>
        @endif

        <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-x-2 text-sm text-zinc-500">
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $post->user->name }}</span>
                <span>{{ '@'.$post->user->username }}</span>
                <span>·</span>
                <a href="{{ route('posts.show', $post) }}" wire:navigate class="hover:underline">
                    <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</time>
                </a>
            </div>

            @if ($showBook)
                <a href="{{ route('books.show', $post->book) }}" wire:navigate class="block truncate text-sm font-semibold hover:underline">
                    {{ $post->book->title }}
                </a>
            @endif

            @if ($post->has_spoiler)
                <div x-data="{ open: false }">
                    <flux:button size="xs" icon="eye" x-show="! open" x-on:click="open = true">ネタバレを含む感想を表示</flux:button>
                    <p x-show="open" x-cloak class="whitespace-pre-line break-words">{{ $post->body }}</p>
                </div>
            @else
                <p class="whitespace-pre-line break-words">{{ $post->body }}</p>
            @endif

            @if ($post->tags->isNotEmpty())
                <div class="flex flex-wrap gap-1">
                    @foreach ($post->tags as $tag)
                        <flux:badge size="sm">#{{ $tag->name }}</flux:badge>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</article>
