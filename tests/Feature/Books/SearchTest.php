<?php

use App\Enums\ReadingStatus;
use App\Livewire\Books\Search;
use App\Models\Book;
use App\Models\User;
use App\Services\GoogleBooks\GoogleBooksClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::fake([
        'www.googleapis.com/books/v1/volumes/vol1*' => Http::response(googleBooksVolume('vol1')),
        'www.googleapis.com/books/v1/volumes?*' => Http::response(['items' => [googleBooksVolume('vol1')]]),
    ]);

    $this->user = User::factory()->create();
});

it('requires login', function () {
    $this->get(route('books.search'))->assertRedirect(route('login'));
});

it('renders the search page', function () {
    $this->actingAs($this->user)->get(route('books.search'))->assertOk()->assertSee('本を探す');
});

it('shows search results', function () {
    Livewire::actingAs($this->user)
        ->test(Search::class)
        ->set('keyword', 'リーダブル')
        ->assertSee('リーダブルコード')
        ->assertSee('Dustin Boswell');
});

it('does not search with a single character', function () {
    Livewire::actingAs($this->user)
        ->test(Search::class)
        ->set('keyword', 'a')
        ->assertDontSee('リーダブルコード');

    Http::assertNothingSent();
});

it('shows an error message when the API fails', function () {
    $this->mock(GoogleBooksClient::class)
        ->shouldReceive('search')
        ->andThrow(new ConnectionException('timeout'));

    Livewire::actingAs($this->user)
        ->test(Search::class)
        ->set('keyword', 'エラー')
        ->assertSee('検索に失敗しました');
});

it('adds a book to the shelf', function () {
    Livewire::actingAs($this->user)
        ->test(Search::class)
        ->set('keyword', 'リーダブル')
        ->call('addToShelf', 'vol1', 'reading')
        ->assertSee('読んでいる');

    $book = Book::where('google_books_id', 'vol1')->sole();

    expect($book->title)->toBe('リーダブルコード')
        ->and($this->user->readingRecords()->sole())
        ->book_id->toBe($book->id)
        ->status->toBe(ReadingStatus::Reading)
        ->started_on->not->toBeNull();
});

it('does not duplicate a book already on the shelf', function () {
    $component = Livewire::actingAs($this->user)->test(Search::class);

    $component->call('addToShelf', 'vol1', 'want');
    $component->call('addToShelf', 'vol1', 'finished');

    expect(Book::count())->toBe(1)
        ->and($this->user->readingRecords()->sole()->status)->toBe(ReadingStatus::Want);
});

it('shares a book record between users', function () {
    $other = User::factory()->create();

    Livewire::actingAs($this->user)->test(Search::class)->call('addToShelf', 'vol1');
    Livewire::actingAs($other)->test(Search::class)->call('addToShelf', 'vol1');

    expect(Book::count())->toBe(1);
});

it('rejects an invalid status', function () {
    Livewire::actingAs($this->user)
        ->test(Search::class)
        ->call('addToShelf', 'vol1', 'invalid')
        ->assertStatus(422);
});
