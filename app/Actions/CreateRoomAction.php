<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Player;
use App\Models\Room;

final readonly class CreateRoomAction
{
    public function handle(string $playerName, string $sessionId): Room
    {
        $code = $this->generateUniqueCode();

        $room = Room::query()->create([
            'code' => $code,
            'initiator_session_id' => $sessionId,
            'status' => 'waiting',
            'current_drawer_order' => 0,
        ]);

        Player::query()->create([
            'room_id' => $room->id,
            'name' => $playerName,
            'session_id' => $sessionId,
            'score' => 0,
            'join_order' => 0,
            'is_active' => true,
        ]);

        return $room;
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = mb_strtoupper(mb_substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5));
        } while (Room::query()->where('code', $code)->whereNull('deleted_at')->exists());

        return $code;
    }
}
