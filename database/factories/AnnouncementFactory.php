<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(5), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(12),
            'body' => fake()->paragraphs(2, true),
            'status' => 'draft',
            'send_push' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published', 'publish_at' => now()->subDay()]);
    }
}
