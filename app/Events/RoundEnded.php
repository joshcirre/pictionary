<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Player;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RoundEnded implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Round $round) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        /** @var Room $room */
        $room = $this->round->room;

        return [new Channel('room.'.$room->code)];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        /** @var Player|null $winner */
        $winner = $this->round->winner;

        return [
            'round_id' => $this->round->id,
            'status' => $this->round->status,
            'word' => $this->round->word,
            'winner_id' => $this->round->winner_player_id,
            'winner_name' => $winner?->name,
        ];
    }
}
