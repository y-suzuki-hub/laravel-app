<?php

namespace Database\Factories;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\ReadingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingRecord>
 */
class ReadingRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'status' => ReadingStatus::Want,
        ];
    }

    public function reading(): static
    {
        return $this->state(fn () => [
            'status' => ReadingStatus::Reading,
            'started_on' => fake()->dateTimeBetween('-1 month'),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn () => [
            'status' => ReadingStatus::Finished,
            'rating' => fake()->numberBetween(1, 5),
            'started_on' => fake()->dateTimeBetween('-3 months', '-1 month'),
            'finished_on' => fake()->dateTimeBetween('-1 month'),
        ]);
    }
}
