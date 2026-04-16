<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class StrokeSynced implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param array<string, mixed> $stroke */
    public function __construct(
        public string $roomCode,
        public array $stroke,
        ?string $socketId = null,
    ) {
        $this->socket = $socketId;
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('room.'.$this->roomCode)];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['stroke' => $this->stroke];
    }
}
