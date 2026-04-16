<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\EndRoundAction;
use App\Models\Round;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class EndRoundJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $roundId) {}

    public function handle(EndRoundAction $endRoundAction): void
    {
        $round = Round::query()->find($this->roundId);

        if (! $round || ! $round->isActive()) {
            return;
        }

        $endRoundAction->handleTimeout($round);
    }
}
