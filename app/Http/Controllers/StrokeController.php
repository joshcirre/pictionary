<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\StrokeSynced;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StrokeController extends Controller
{
    public function index(string $code): JsonResponse
    {
        $room = Room::query()->where('code', $code)->whereIn('status', ['waiting', 'active'])->firstOrFail();

        /** @var \App\Models\Round|null $activeRound */
        $activeRound = $room->currentRound()->first();

        return response()->json(['strokes' => $activeRound instanceof \App\Models\Round ? ($activeRound->strokes ?? []) : []]);
    }

    public function store(Request $request, string $code): JsonResponse
    {
        $room = Room::query()->where('code', $code)->where('status', 'active')->firstOrFail();

        /** @var \App\Models\Player|null $player */
        $player = $room->players()->where('session_id', session()->getId())->first();

        /** @var \App\Models\Round|null $activeRound */
        $activeRound = $room->currentRound()->first();

        if (! $player || ! $activeRound || $activeRound->drawer_player_id !== $player->id) {
            return response()->json(['ok' => false], 403);
        }

        /** @var array<string, mixed> $stroke */
        $stroke = $request->validate([
            'id' => 'required|string',
            'points' => 'required|array',
            'points.*.x' => 'required|numeric|min:0|max:1',
            'points.*.y' => 'required|numeric|min:0|max:1',
            'width_ratio' => 'nullable|numeric',
            'incremental' => 'nullable|boolean',
        ]);

        // Persist stroke so late-joiners and refreshers can replay it
        /** @var array<string, array<string, mixed>> $existing */
        $existing = $activeRound->strokes ?? [];
        /** @var string $strokeId */
        $strokeId = $stroke['id'];

        if (isset($existing[$strokeId])) {
            /** @var array<int, array<string, float>> $newPoints */
            $newPoints = $stroke['points'];
            /** @var array<int, array<string, float>> $oldPoints */
            $oldPoints = $existing[$strokeId]['points'] ?? [];
            $existing[$strokeId]['points'] = array_merge($oldPoints, $newPoints);
        } else {
            $existing[$strokeId] = [
                'id' => $strokeId,
                'points' => $stroke['points'],
                'width_ratio' => $stroke['width_ratio'] ?? 0.012,
            ];
        }

        $activeRound->update(['strokes' => $existing]);

        StrokeSynced::dispatch($room->code, $stroke, $request->header('X-Socket-ID'));

        return response()->json(['ok' => true]);
    }
}
