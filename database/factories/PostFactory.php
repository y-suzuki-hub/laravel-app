<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'body' => fake()->realText(200),
            'has_spoiler' => false,
        ];
    }

    public function spoiler(): static
    {
        return $this->state(fn () => ['has_spoiler' => true]);
    }
}
