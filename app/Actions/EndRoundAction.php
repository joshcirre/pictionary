<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RoundEnded;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;

final readonly class EndRoundAction
{
    public function __construct(private StartRoundAction $startRoundAction) {}

    public function handle(Round $round, string $status, ?Player $winner = null): void
    {
        if (! $round->isActive()) {
            return;
        }

        $round->update([
            'status' => $status,
            'winner_player_id' => $winner?->id,
        ]);

        if ($winner instanceof Player) {
            $winner->increment('score');
        }

        $round->load(['drawer', 'room', 'winner']);

        RoundEnded::dispatch($round);

        /** @var Room $room */
        $room = $round->room;
        $room->increment('current_drawer_order');
        $room->refresh();

        $this->startRoundAction->handle($room);
    }

    public function handleTimeout(Round $round): void
    {
        $this->handle($round, 'timeout');
    }
}
