<?php

use App\Livewire\Books;
use App\Livewire\Dashboard;
use App\Livewire\Posts;
use App\Livewire\Shelf;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('books/search', Books\Search::class)->name('books.search');
    Route::livewire('shelf', Shelf\Index::class)->name('shelf.index');

    Route::livewire('books/{book}/posts/create', Posts\Create::class)->whereNumber('book')->name('posts.create');
    Route::livewire('posts/{post}/edit', Posts\Edit::class)->whereNumber('post')->name('posts.edit');
});

// ログインしていなくても閲覧できるページ
Route::livewire('books/{book}', Books\Show::class)->whereNumber('book')->name('books.show');
Route::livewire('posts/{post}', Posts\Show::class)->whereNumber('post')->name('posts.show');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
