<?php

use App\Enums\ReadingStatus;
use App\Livewire\Shelf\Index;
use App\Models\Book;
use App\Models\ReadingRecord;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires login', function () {
    $this->get(route('shelf.index'))->assertRedirect(route('login'));
});

it('shows only my books and filters by status', function () {
    ReadingRecord::factory()->for($this->user)->for(Book::factory()->state(['title' => '読書中の本']))->reading()->create();
    ReadingRecord::factory()->for($this->user)->for(Book::factory()->state(['title' => '読了した本']))->finished()->create();
    ReadingRecord::factory()->for(Book::factory()->state(['title' => '他人の本']))->create();

    $this->actingAs($this->user)->get(route('shelf.index'))
        ->assertOk()
        ->assertSee('読書中の本')
        ->assertSee('読了した本')
        ->assertDontSee('他人の本');

    Livewire::actingAs($this->user)
        ->test(Index::class)
        ->set('status', 'reading')
        ->assertSee('読書中の本')
        ->assertDontSee('読了した本');
});

it('records dates when the status changes', function () {
    $record = ReadingRecord::factory()->for($this->user)->create();

    Livewire::actingAs($this->user)->test(Index::class)
        ->call('changeStatus', $record->id, 'finished');

    $record->refresh();

    expect($record->status)->toBe(ReadingStatus::Finished)
        ->and($record->started_on->isToday())->toBeTrue()
        ->and($record->finished_on->isToday())->toBeTrue();

    Livewire::actingAs($this->user)->test(Index::class)
        ->call('changeStatus', $record->id, 'reading');

    expect($record->refresh()->finished_on)->toBeNull();
});

it('sets and clears a rating', function () {
    $record = ReadingRecord::factory()->for($this->user)->create();

    $component = Livewire::actingAs($this->user)->test(Index::class);

    $component->call('rate', $record->id, 4);
    expect($record->refresh()->rating)->toBe(4);

    $component->call('rate', $record->id, 4);
    expect($record->refresh()->rating)->toBeNull();

    $component->call('rate', $record->id, 6)->assertStatus(422);
});

it('removes a book from the shelf', function () {
    $record = ReadingRecord::factory()->for($this->user)->create();

    Livewire::actingAs($this->user)->test(Index::class)->call('remove', $record->id);

    expect(ReadingRecord::find($record->id))->toBeNull();
});

it('forbids changing another user\'s shelf', function (string $method, array $args) {
    $record = ReadingRecord::factory()->create();

    Livewire::actingAs($this->user)->test(Index::class)
        ->call($method, $record->id, ...$args)
        ->assertForbidden();

    expect($record->fresh())->not->toBeNull()
        ->status->toBe(ReadingStatus::Want)
        ->rating->toBeNull();
})->with([
    'change status' => ['changeStatus', ['finished']],
    'rate' => ['rate', [5]],
    'remove' => ['remove', []],
]);
