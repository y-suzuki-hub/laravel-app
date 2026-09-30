<?php

use App\Models\Book;
use App\Models\Post;
use App\Models\ReadingRecord;
use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows books I am reading and recent posts', function () {
    $user = User::factory()->create();
    ReadingRecord::factory()->for($user)->for(Book::factory()->state(['title' => '今読んでいる本']))->reading()->create();
    ReadingRecord::factory()->for($user)->for(Book::factory()->state(['title' => '積読の本']))->create();
    Post::factory()->count(3)->create();
    Post::factory()->create(['body' => '最新の感想']);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('今読んでいる本')
        ->assertDontSee('積読の本')
        ->assertSee('最新の感想');
});
