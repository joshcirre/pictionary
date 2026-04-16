<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
final class RoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => \App\Models\Room::factory(),
            'drawer_player_id' => \App\Models\Player::factory(),
            'word' => fake()->word(),
            'status' => 'active',
            'ends_at' => now()->addSeconds(30),
        ];
    }
}
