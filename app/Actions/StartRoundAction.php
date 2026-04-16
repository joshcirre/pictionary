<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RoomUpdated;
use App\Events\RoundStarted;
use App\Jobs\EndRoundJob;
use App\Models\Room;
use App\Models\Round;
use App\Services\WordService;

final readonly class StartRoundAction
{
    public function __construct(private WordService $wordService) {}

    public function handle(Room $room): ?Round
    {
        $activePlayers = $room->activePlayers()->get();

        if ($activePlayers->count() < 2) {
            return null;
        }

        $drawerIndex = $room->current_drawer_order % $activePlayers->count();

        /** @var \App\Models\Player $drawer */
        $drawer = $activePlayers[$drawerIndex];

        $round = Round::query()->create([
            'room_id' => $room->id,
            'drawer_player_id' => $drawer->id,
            'word' => $this->wordService->random(),
            'status' => 'active',
            'ends_at' => now()->addSeconds(30),
        ]);

        $room->update(['status' => 'active']);

        $round->load(['drawer', 'room']);

        RoundStarted::dispatch($round);

        $freshRoom = $room->fresh();
        if ($freshRoom instanceof Room) {
            RoomUpdated::dispatch($freshRoom);
        }

        EndRoundJob::dispatch($round->id)->delay(now()->addSeconds(32));

        return $round;
    }
}
