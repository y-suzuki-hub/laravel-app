<?php

use App\Livewire\Books\Show;
use App\Models\Book;
use App\Models\Post;
use App\Models\ReadingRecord;
use App\Models\User;
use Livewire\Livewire;

it('is visible to guests with posts and stats', function () {
    $book = Book::factory()->create(['title' => 'テスト駆動開発']);
    Post::factory()->for($book)->create(['body' => 'とても良い本']);
    ReadingRecord::factory()->for($book)->finished()->create(['rating' => 4]);
    ReadingRecord::factory()->for($book)->finished()->create(['rating' => 5]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('テスト駆動開発')
        ->assertSee('とても良い本')
        ->assertSee('2 人が本棚に登録')
        ->assertSee('4.5')
        ->assertDontSee('感想を書く');
});

it('lets a logged in user add the book to the shelf', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    Livewire::actingAs($user)
        ->test(Show::class, ['book' => $book])
        ->call('addToShelf')
        ->assertSee('本棚: 読みたい');

    expect($user->readingRecords()->where('book_id', $book->id)->exists())->toBeTrue();
});

it('returns 404 for a missing book', function () {
    $this->get('/books/999')->assertNotFound();
});
