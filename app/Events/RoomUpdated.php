<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Player;
use App\Models\Room;
use App\Models\Round;
use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Room $room) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('room.'.$this->room->code)];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $this->room->load(['activePlayers', 'currentRound.drawer']);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Player> $activePlayers */
        $activePlayers = $this->room->activePlayers;

        /** @var Round|null $currentRound */
        $currentRound = $this->room->currentRound;

        /** @var Player|null $drawer */
        $drawer = $currentRound?->drawer;

        /** @var Carbon|null $endsAt */
        $endsAt = $currentRound?->ends_at;

        return [
            'status' => $this->room->status,
            'players' => $activePlayers->map(fn (Player $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'score' => $p->score,
                'join_order' => $p->join_order,
            ])->values()->toArray(),
            'current_round' => $currentRound ? [
                'id' => $currentRound->id,
                'drawer_id' => $currentRound->drawer_player_id,
                'drawer_name' => $drawer?->name,
                'ends_at' => $endsAt?->toISOString(),
                'status' => $currentRound->status,
            ] : null,
        ];
    }
}
