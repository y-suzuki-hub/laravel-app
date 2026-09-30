<?php

use App\Services\GoogleBooks\BookData;

it('maps a Google Books volume to book data', function () {
    $data = BookData::fromApi(googleBooksVolume('abc'));

    expect($data)
        ->googleBooksId->toBe('abc')
        ->title->toBe('リーダブルコード')
        ->authors->toBe('Dustin Boswell, Trevor Foucher')
        ->publisher->toBe('オライリージャパン')
        ->publishedDate->toBe('2012-06-23')
        ->isbn13->toBe('9784873115658')
        ->pageCount->toBe(237)
        ->description->toBe('より良いコードを書くためのシンプルで実践的なテクニック')
        ->thumbnailUrl->toBe('https://books.google.com/books/content?id=abc');
});

it('handles volumes with missing fields', function () {
    $data = BookData::fromApi(['id' => 'min', 'volumeInfo' => ['title' => 'Only Title']]);

    expect($data)
        ->title->toBe('Only Title')
        ->authors->toBeNull()
        ->isbn13->toBeNull()
        ->thumbnailUrl->toBeNull()
        ->pageCount->toBeNull();
});
