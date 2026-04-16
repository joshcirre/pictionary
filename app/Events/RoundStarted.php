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

final class RoundStarted implements ShouldBroadcastNow
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
        /** @var Player $drawer */
        $drawer = $this->round->drawer;

        /** @var Carbon $endsAt */
        $endsAt = $this->round->ends_at;

        return [
            'round_id' => $this->round->id,
            'drawer_id' => $this->round->drawer_player_id,
            'drawer_name' => $drawer->name,
            'ends_at' => $endsAt->toISOString(),
            'word' => $this->round->word,
        ];
    }
}
