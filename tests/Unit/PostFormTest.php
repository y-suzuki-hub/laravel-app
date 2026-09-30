<?php

use App\Livewire\Forms\PostForm;

it('parses tags separated by spaces, commas and hashes', function () {
    expect(PostForm::parseTags('#技術書 Laravel,PHP、 設計　laravel'))
        ->toBe(['技術書', 'laravel', 'php', '設計']);
});

it('returns no tags for blank input', function () {
    expect(PostForm::parseTags('  , '))->toBe([]);
});
