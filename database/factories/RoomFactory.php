<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
final class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->regexify('[A-Z2-9]{5}')),
            'initiator_session_id' => fake()->uuid(),
            'status' => 'waiting',
            'current_drawer_order' => 0,
        ];
    }
}
