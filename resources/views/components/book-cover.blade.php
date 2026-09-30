@props(['url' => null, 'title' => ''])

@if ($url)
    <img src="{{ $url }}" alt="{{ $title }}" loading="lazy" {{ $attributes->merge(['class' => 'rounded-sm object-cover shadow-sm']) }}>
@else
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center rounded-sm bg-zinc-200 text-zinc-400 dark:bg-zinc-700']) }}>
        <flux:icon.book-open class="size-6" />
    </div>
@endif
