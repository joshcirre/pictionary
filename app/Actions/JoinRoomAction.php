<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RoomUpdated;
use App\Models\Player;
use App\Models\Room;

final readonly class JoinRoomAction
{
    public function handle(Room $room, string $playerName, string $sessionId): Player
    {
        $existing = Player::query()
            ->where('room_id', $room->id)
            ->where('session_id', $sessionId)
            ->withTrashed()
            ->first();

        if ($existing instanceof Player) {
            $existing->restore();
            $existing->update(['name' => $playerName, 'is_active' => true]);

            $fresh = $room->fresh();
            if ($fresh instanceof Room) {
                RoomUpdated::dispatch($fresh);
            }

            return $existing;
        }

        $maxOrder = Player::query()->where('room_id', $room->id)->max('join_order');
        $joinOrder = is_int($maxOrder) ? $maxOrder + 1 : 1;

        /** @var Player $player */
        $player = Player::query()->create([
            'room_id' => $room->id,
            'name' => $playerName,
            'session_id' => $sessionId,
            'score' => 0,
            'join_order' => $joinOrder,
            'is_active' => true,
        ]);

        $fresh = $room->fresh();
        if ($fresh instanceof Room) {
            RoomUpdated::dispatch($fresh);
        }

        return $player;
    }
}
