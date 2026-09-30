@props(['rating' => null])

@if ($rating)
    <span {{ $attributes->merge(['class' => 'inline-flex text-amber-500']) }} title="評価 {{ $rating }} / 5">
        @for ($i = 1; $i <= 5; $i++)
            <flux:icon.star variant="{{ $i <= $rating ? 'solid' : 'outline' }}" class="size-4" />
        @endfor
    </span>
@endif
