<form wire:submit="save" class="space-y-6">
    <flux:textarea wire:model="form.body" label="感想・メモ" rows="8" placeholder="印象に残ったこと、学んだことなど" />

    <flux:input wire:model="form.tags" label="タグ" description="スペースかカンマで区切って 5 個まで（例: 技術書 Laravel）" placeholder="技術書 Laravel" />

    <flux:checkbox wire:model="form.has_spoiler" label="ネタバレを含む" description="一覧では折りたたんで表示されます" />

    <div class="flex gap-2">
        <flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button>
        <flux:button :href="$cancelUrl" variant="ghost" wire:navigate>キャンセル</flux:button>
    </div>
</form>
