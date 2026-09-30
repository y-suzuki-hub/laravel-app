<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Post;
use App\Models\ReadingRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $me = User::factory()->create([
            'name' => 'Test User',
            'username' => 'test_user',
            'email' => 'test@example.com',
        ]);

        $users = User::factory(5)->create();
        $books = Book::factory(12)->create();

        // 自分の本棚: 読んでいる本・読み終わった本・読みたい本
        $books->take(3)->each(fn (Book $book) => ReadingRecord::factory()->for($me)->for($book)->reading()->create());
        $books->slice(3, 3)->each(fn (Book $book) => ReadingRecord::factory()->for($me)->for($book)->finished()->create());
        $books->slice(6, 2)->each(fn (Book $book) => ReadingRecord::factory()->for($me)->for($book)->create());

        // 他のユーザーの本棚と感想
        $tags = ['技術書', 'ビジネス', '小説', '設計', 'laravel'];

        foreach ($users as $user) {
            $books->random(4)->each(function (Book $book) use ($user, $tags) {
                ReadingRecord::factory()->for($user)->for($book)->finished()->create();

                Post::factory()->for($user)->for($book)->create()
                    ->syncTagNames(fake()->randomElements($tags, fake()->numberBetween(0, 2)));
            });
        }
    }
}
