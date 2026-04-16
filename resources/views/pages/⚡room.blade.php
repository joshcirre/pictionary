<?php

declare(strict_types=1);

use App\Actions\EndRoundAction;
use App\Actions\JoinRoomAction;
use App\Actions\StartRoundAction;
use App\Events\RoomUpdated;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public Room $room;
    public ?Player $currentPlayer = null;
    public ?Round $activeRound = null;

    /** @var array<int, array<string, mixed>> */
    public array $players = [];

    public string $guess = '';
    public string $roundEndsAt = '';
    public bool $wrongGuess = false;
    public bool $isDrawer = false;

    public string $roundEndStatus = '';
    public string $roundEndWord = '';
    public string $roundEndWinnerName = '';

    public function mount(Room $room, JoinRoomAction $joinRoomAction, StartRoundAction $startRoundAction): void
    {
        if ($room->isEnded()) {
            $this->redirect(route('home'), navigate: true);

            return;
        }

        $playerName = session('player_name');

        if (! $playerName) {
            $this->redirect(route('home'), navigate: true);

            return;
        }

        $this->room = $room;
        $this->currentPlayer = $joinRoomAction->handle($room, $playerName, session()->getId());
        $this->syncState();

        if ($room->isWaiting() && $room->activePlayers()->count() >= 2) {
            $startRoundAction->handle($room);
            $this->room->refresh();
            $this->syncState();
        }
    }

    public function syncState(): void
    {
        $this->room->refresh();
        $this->players = $this->room->activePlayers
            ->map(
                fn (Player $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'score' => $p->score,
                ],
            )
            ->values()
            ->toArray();

        $this->activeRound = $this->room->currentRound;
        $this->roundEndsAt = $this->activeRound?->ends_at?->toISOString() ?? '';
        $this->isDrawer = $this->isDrawer();
    }

    #[On('echo:room.{room.code},RoomUpdated')]
    public function onRoomUpdated(): void
    {
        $this->syncState();

        if ($this->room->isEnded()) {
            $this->redirect(route('home'), navigate: true);
        }
    }

    #[On('echo:room.{room.code},RoundStarted')]
    public function onRoundStarted(): void
    {
        $this->wrongGuess = false;
        $this->guess = '';
        $this->syncState();
        $this->dispatch('timer-reset');
    }

    #[On('echo:room.{room.code},RoundEnded')]
    public function onRoundEnded(): void
    {
        // $this->activeRound is hydrated fresh from DB at request start — it's the round that just ended
        $endedRound = $this->activeRound;
        $endedRound?->load('winner');

        $this->roundEndsAt = '';
        $this->roundEndStatus = $endedRound?->status ?? 'timeout';
        $this->roundEndWord = $endedRound?->word ?? '';
        $this->roundEndWinnerName = $endedRound?->winner?->name ?? '';
        $this->syncState();
        $this->dispatch('round-ended');
    }

    public function submitGuess(EndRoundAction $endRoundAction): void
    {
        if (! $this->activeRound || ! $this->activeRound->isActive()) {
            return;
        }

        if (! $this->currentPlayer || $this->currentPlayer->id === $this->activeRound->drawer_player_id) {
            return;
        }

        if (mb_strtolower(trim($this->guess)) === mb_strtolower($this->activeRound->word)) {
            $word = $this->activeRound->word;
            $winnerName = $this->currentPlayer->name;
            $endRoundAction->handle($this->activeRound, 'correct', $this->currentPlayer);
            // Winner is excluded from Echo broadcasts (X-Socket-ID), so handle overlay locally
            $this->roundEndsAt = '';
            $this->roundEndStatus = 'correct';
            $this->roundEndWord = $word;
            $this->roundEndWinnerName = $winnerName;
            $this->syncState();
            $this->dispatch('round-ended');
            $this->dispatch('timer-reset');
        } else {
            $this->wrongGuess = true;
        }

        $this->guess = '';
    }

    public function endGame(): void
    {
        if ($this->room->initiator_session_id !== session()->getId()) {
            return;
        }

        $this->room->update(['status' => 'ended']);

        $fresh = $this->room->fresh();
        if ($fresh instanceof Room) {
            RoomUpdated::dispatch($fresh);
        }

        $this->redirect(route('home'), navigate: true);
    }

    public function isDrawer(): bool
    {
        return $this->activeRound instanceof Round &&
            $this->currentPlayer instanceof Player &&
            $this->activeRound->drawer_player_id === $this->currentPlayer->id;
    }

    public function isInitiator(): bool
    {
        return $this->room->initiator_session_id === session()->getId();
    }

    public function getWordForDrawer(): string
    {
        return $this->isDrawer() && $this->activeRound instanceof Round ? $this->activeRound->word : '';
    }
};

?>

<div
    class="flex h-full flex-col overflow-hidden"
    x-data="pictionaryRoom(@js($room->code), @js($this->isDrawer()), @js($this->getWordForDrawer()))"
    x-init="init()"
    x-on:timer-reset.window="resetTimer()"
>
    {{-- Header --}}
    <header
        class="flex shrink-0 items-center justify-between border-b border-zinc-200 bg-white px-4 py-2.5 sm:px-6 dark:border-zinc-800 dark:bg-zinc-900"
    >
        <div class="flex items-center gap-4">
            <span class="font-mono text-xs font-bold tracking-[0.2em] text-zinc-700 dark:text-zinc-300">PICTIONARY</span>
            <span class="hidden text-zinc-300 sm:inline dark:text-zinc-700">·</span>
            <span class="hidden font-mono text-xs tracking-widest text-zinc-400 sm:inline dark:text-zinc-500">
                ROOM
                <span class="text-zinc-700 dark:text-zinc-300">{{ $room->code }}</span>
            </span>
        </div>

        {{-- Timer display --}}
        <div class="font-mono text-xs tracking-widest text-zinc-400 dark:text-zinc-500">
            @if ($this->room->isWaiting())
                <span class="text-zinc-400 dark:text-zinc-500">WAITING FOR PLAYERS</span>
            @elseif ($roundEndsAt)
                DRAWING
                <span
                    class="tabular-nums"
                    :class="secondsLeft <= 10 ? 'text-red-500' : 'text-zinc-900 dark:text-zinc-100'"
                    x-text="secondsLeft"
                ></span>
                S
            @endif
        </div>

        <div class="flex items-center gap-3">
            @if ($this->activeRound)
                @if ($this->isDrawer())
                    <span class="border-l-2 border-amber-400 pl-3 font-mono text-[10px] tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                        DRAW ·
                        <span class="font-bold text-amber-500 uppercase">{{ $this->getWordForDrawer() }}</span>
                    </span>
                @else
                    <span class="font-mono text-[10px] tracking-[0.2em] text-zinc-400 dark:text-zinc-500">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $this->activeRound->drawer->name }}</span>
                        IS DRAWING
                    </span>
                @endif
            @endif

            @if ($this->isInitiator() && ! $this->room->isEnded())
                <button
                    wire:click="endGame"
                    wire:confirm="End the game for everyone?"
                    class="border border-red-200 px-3 py-1 font-mono text-[10px] tracking-[0.15em] text-red-500 transition-colors hover:border-red-500 hover:bg-red-50 dark:border-red-900 dark:hover:border-red-700 dark:hover:bg-red-950"
                >
                    END GAME
                </button>
            @endif
        </div>
    </header>

    {{-- Timer progress bar --}}
    <div class="h-0.5 w-full shrink-0 bg-zinc-100 dark:bg-zinc-800">
        @if ($roundEndsAt)
            <div
                class="h-full transition-all duration-1000"
                :class="secondsLeft > 20 ? 'bg-emerald-500' : (secondsLeft > 10 ? 'bg-amber-400' : 'bg-red-500')"
                :style="`width: ${(secondsLeft / 30) * 100}%`"
            ></div>
        @endif
    </div>

    {{-- Main area --}}
    <div class="flex flex-1 overflow-hidden">
        {{-- Canvas + guess --}}
        <main class="relative flex flex-1 flex-col items-center justify-center overflow-hidden bg-stone-100 p-6 dark:bg-zinc-950">
            {{-- Round end overlay — shown via local Alpine state triggered by 'round-ended' browser event --}}
            <div
                wire:ignore
                x-show="overlayVisible"
                x-transition:enter="transition duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 z-20 flex items-center justify-center bg-stone-100/90 backdrop-blur-sm dark:bg-zinc-950/90"
                style="display: none"
            >
                <div class="border-l-4 border-amber-400 pl-8">
                    <template x-if="$wire.roundEndStatus === 'correct'">
                        <div>
                            <p class="mb-1 font-mono text-[10px] tracking-[0.25em] text-emerald-600">CORRECT GUESS</p>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100" x-text="$wire.roundEndWinnerName + ' got it!'"></p>
                        </div>
                    </template>
                    <template x-if="$wire.roundEndStatus !== 'correct'">
                        <div>
                            <p class="mb-1 font-mono text-[10px] tracking-[0.25em] text-red-500">TIME'S UP</p>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">Nobody guessed it.</p>
                        </div>
                    </template>
                    <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                        The word was
                        <span class="font-bold text-amber-500" x-text="$wire.roundEndWord"></span>
                    </p>
                    <p class="mt-4 font-mono text-[10px] tracking-widest text-zinc-400 dark:text-zinc-500">NEXT ROUND STARTING...</p>
                </div>
            </div>

            {{-- Waiting state --}}

            @if ($this->room->isWaiting())
                <div class="border-l-4 border-zinc-300 pl-6 text-center dark:border-zinc-700">
                    <p class="mb-1 font-mono text-[10px] tracking-[0.25em] text-zinc-400 dark:text-zinc-500">ROOM CODE</p>
                    <p class="font-mono text-6xl font-bold tracking-[0.3em] text-zinc-900 dark:text-zinc-100">{{ $room->code }}</p>
                    <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Share this code — game starts with 2+ players</p>
                    <div class="mt-2 flex items-center justify-center gap-1.5">
                        <span class="size-1.5 animate-pulse rounded-full bg-amber-400"></span>
                        <span class="font-mono text-[10px] tracking-widest text-zinc-400 dark:text-zinc-500">
                            {{ $room->activePlayers()->count() }} PLAYER{{ $room->activePlayers()->count() === 1 ? '' : 'S' }} JOINED
                        </span>
                    </div>
                </div>
            @else
                {{-- Drawing canvas --}}
                <div
                    class="relative w-full max-w-2xl overflow-hidden rounded bg-white shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-800"
                    style="aspect-ratio: 4/3"
                >
                    <canvas wire:ignore x-ref="displayCanvas" class="absolute inset-0 h-full w-full"></canvas>
                    <canvas
                        wire:ignore
                        x-ref="drawCanvas"
                        :class="$wire.isDrawer ? 'cursor-crosshair touch-none' : 'pointer-events-none'"
                        class="absolute inset-0 h-full w-full"
                        @pointerdown="startStroke($event)"
                        @pointermove="continueStroke($event)"
                        @pointerup="endStroke($event)"
                        @pointercancel="cancelStroke($event)"
                        @lostpointercapture="cancelStroke($event)"
                    ></canvas>
                </div>

                {{-- Guess input --}}
                @if (! $this->isDrawer())
                    <form wire:submit="submitGuess" class="mt-4 flex w-full max-w-2xl flex-col gap-0">
                        <div class="flex gap-0">
                            <input
                                wire:model="guess"
                                type="text"
                                placeholder="Type your guess and press enter..."
                                autocomplete="off"
                                class="{{ $wrongGuess ? 'border-red-400 dark:border-red-600' : '' }} flex-1 border-b border-zinc-300 bg-transparent py-2.5 text-sm text-zinc-900 placeholder-zinc-300 transition-colors outline-none focus:border-amber-400 dark:border-zinc-700 dark:text-zinc-100 dark:placeholder-zinc-600 dark:focus:border-amber-400"
                            />
                            <button
                                type="submit"
                                class="border-b border-zinc-300 px-4 font-mono text-[10px] tracking-[0.2em] text-zinc-400 transition-colors hover:border-amber-400 hover:text-zinc-700 dark:border-zinc-700 dark:hover:text-zinc-300"
                            >
                                GUESS
                            </button>
                        </div>
                        @if ($wrongGuess)
                            <p class="mt-1.5 font-mono text-[10px] text-red-500">WRONG — KEEP TRYING</p>
                        @endif
                    </form>
                @else
                    <p class="mt-4 font-mono text-[10px] tracking-widest text-zinc-400 dark:text-zinc-500">DRAW · OTHERS ARE GUESSING</p>
                @endif
            @endif
        </main>

        {{-- Scoreboard sidebar --}}
        <aside class="flex w-52 shrink-0 flex-col border-l border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <p class="font-mono text-[10px] tracking-[0.25em] text-zinc-400 dark:text-zinc-500">PLAYERS</p>
            </div>
            <div class="flex-1 overflow-y-auto p-3">
                <div class="space-y-1">
                    @foreach ($players as $player)
                        @php
                            $isDrawing = $this->activeRound && $this->activeRound->drawer_player_id === $player['id'];
                            $isMe = $currentPlayer && $player['id'] === $currentPlayer->id;
                        @endphp

                        <div
                            class="{{ $isDrawing ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/30' : 'border-transparent hover:border-zinc-300 dark:hover:border-zinc-700' }} flex items-center justify-between border-l-2 py-2 pr-2 pl-3 transition-colors"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $player['name'] }}
                                    @if ($isMe)
                                        <span class="font-mono text-[9px] tracking-widest text-zinc-400 dark:text-zinc-500">YOU</span>
                                    @endif
                                </p>
                                @if ($isDrawing)
                                    <p class="font-mono text-[9px] tracking-widest text-amber-500">DRAWING</p>
                                @endif
                            </div>
                            <span
                                class="{{ $isDrawing ? 'text-amber-500' : 'text-zinc-400 dark:text-zinc-500' }} ml-2 shrink-0 font-mono text-sm font-bold"
                            >
                                {{ $player['score'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>
    </div>
</div>
