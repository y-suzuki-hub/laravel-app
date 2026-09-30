<div class="mx-auto w-full max-w-2xl space-y-4">
    <x-post-card :post="$post" />

    @can('update', $post)
        <div class="flex gap-2">
            <flux:button size="sm" icon="pencil-square" :href="route('posts.edit', $post)" wire:navigate>編集</flux:button>
            <flux:button size="sm" variant="danger" icon="trash" wire:click="delete" wire:confirm="この感想を削除しますか？">削除</flux:button>
        </div>
    @endcan
</div>
