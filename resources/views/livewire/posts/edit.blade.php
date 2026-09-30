<div class="mx-auto w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">感想を編集</flux:heading>

    <p class="font-semibold">{{ $post->book->title }}</p>

    @include('livewire.posts._form', ['submitLabel' => '更新する', 'cancelUrl' => route('posts.show', $post)])
</div>
