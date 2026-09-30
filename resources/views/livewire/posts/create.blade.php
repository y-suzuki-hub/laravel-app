<div class="mx-auto w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">感想を書く</flux:heading>

    <div class="flex items-center gap-3">
        <x-book-cover :url="$book->thumbnail_url" :title="$book->title" class="h-16 w-11" />
        <div>
            <p class="font-semibold">{{ $book->title }}</p>
            <p class="text-sm text-zinc-500">{{ $book->authors }}</p>
        </div>
    </div>

    @include('livewire.posts._form', ['submitLabel' => '投稿する', 'cancelUrl' => route('books.show', $book)])
</div>
