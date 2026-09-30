<?php

use App\Services\GoogleBooks\GoogleBooksClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('searches books and caches the result', function () {
    Http::fake([
        'www.googleapis.com/books/v1/volumes*' => Http::response(['items' => [googleBooksVolume('a'), googleBooksVolume('b')]]),
    ]);

    $client = new GoogleBooksClient(apiKey: 'test-key');

    expect($client->search('リーダブル'))->toHaveCount(2)
        ->and($client->search('リーダブル')->first()->googleBooksId)->toBe('a');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['q'] === 'リーダブル' && $request['key'] === 'test-key');
});

it('returns an empty collection when nothing matches', function () {
    Http::fake(['*' => Http::response(['totalItems' => 0])]);

    expect((new GoogleBooksClient(apiKey: null))->search('zzzz'))->toBeEmpty();
});

it('does not call the API for a blank keyword', function () {
    Http::fake();

    expect((new GoogleBooksClient(apiKey: null))->search('   '))->toBeEmpty();

    Http::assertNothingSent();
});

it('finds a volume by id and returns null when it does not exist', function () {
    Http::fake([
        'www.googleapis.com/books/v1/volumes/exists' => Http::response(googleBooksVolume('exists')),
        'www.googleapis.com/books/v1/volumes/missing' => Http::response([], 404),
    ]);

    $client = new GoogleBooksClient(apiKey: null);

    expect($client->find('exists')?->googleBooksId)->toBe('exists')
        ->and($client->find('missing'))->toBeNull();
});
