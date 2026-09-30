<?php

use App\Livewire\Posts\Create;
use App\Livewire\Posts\Edit;
use App\Livewire\Posts\Show;
use App\Models\Book;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create();
});

it('redirects guests away from the create page', function () {
    $this->get(route('posts.create', $this->book))->assertRedirect(route('login'));
});

it('creates a post with tags', function () {
    Livewire::actingAs($this->user)
        ->test(Create::class, ['book' => $this->book])
        ->set('form.body', '読みやすいコードの基本が身についた')
        ->set('form.tags', '技術書 #Laravel')
        ->set('form.has_spoiler', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('posts.show', Post::first()));

    $post = $this->user->posts()->sole();

    expect($post)
        ->book_id->toBe($this->book->id)
        ->body->toBe('読みやすいコードの基本が身についた')
        ->has_spoiler->toBeTrue()
        ->and($post->tags->pluck('name')->sort()->values()->all())->toBe(['laravel', '技術書']);
});

it('reuses existing tags', function () {
    Tag::factory()->create(['name' => '技術書']);

    Livewire::actingAs($this->user)
        ->test(Create::class, ['book' => $this->book])
        ->set('form.body', '感想')
        ->set('form.tags', '技術書')
        ->call('save');

    expect(Tag::count())->toBe(1);
});

it('validates the post', function (array $input, string $field) {
    Livewire::actingAs($this->user)
        ->test(Create::class, ['book' => $this->book])
        ->set('form.body', $input['body'] ?? '感想')
        ->set('form.tags', $input['tags'] ?? '')
        ->call('save')
        ->assertHasErrors($field);

    expect(Post::count())->toBe(0);
})->with([
    'body is required' => [['body' => ''], 'form.body'],
    'body is too long' => [['body' => str_repeat('あ', 2001)], 'form.body'],
    'too many tags' => [['tags' => 'a b c d e f'], 'form.tags'],
    'tag is too long' => [['tags' => str_repeat('a', 31)], 'form.tags'],
]);

it('shows a post to guests', function () {
    $post = Post::factory()->for($this->book)->create(['body' => '公開された感想']);

    $this->get(route('posts.show', $post))
        ->assertOk()
        ->assertSee('公開された感想')
        ->assertDontSee('編集');
});

it('lets the author edit a post', function () {
    $post = Post::factory()->for($this->user)->create();
    $post->syncTagNames(['old']);

    Livewire::actingAs($this->user)
        ->test(Edit::class, ['post' => $post])
        ->assertSet('form.tags', 'old')
        ->set('form.body', '書き直した感想')
        ->set('form.tags', 'new')
        ->call('save')
        ->assertRedirect(route('posts.show', $post));

    $post->refresh();

    expect($post->body)->toBe('書き直した感想')
        ->and($post->tags->pluck('name')->all())->toBe(['new']);
});

it('forbids editing someone else\'s post', function () {
    $post = Post::factory()->create();

    $this->actingAs($this->user)->get(route('posts.edit', $post))->assertForbidden();
});

it('lets the author delete a post', function () {
    $post = Post::factory()->for($this->user)->for($this->book)->create();

    Livewire::actingAs($this->user)
        ->test(Show::class, ['post' => $post])
        ->call('delete')
        ->assertRedirect(route('books.show', $this->book));

    expect(Post::count())->toBe(0);
});

it('forbids deleting someone else\'s post', function () {
    $post = Post::factory()->create();

    Livewire::actingAs($this->user)
        ->test(Show::class, ['post' => $post])
        ->call('delete')
        ->assertForbidden();

    expect(Post::count())->toBe(1);
});
