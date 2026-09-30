<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'google_books_id' => fake()->unique()->regexify('[A-Za-z0-9_-]{12}'),
            'isbn_13' => fake()->isbn13(),
            'title' => fake()->sentence(3),
            'authors' => fake()->name(),
            'publisher' => fake()->company(),
            'published_date' => fake()->date('Y-m-d'),
            'description' => fake()->paragraph(),
            'thumbnail_url' => null,
            'page_count' => fake()->numberBetween(80, 600),
        ];
    }
}
