<?php

declare(strict_types=1);

use App\Actions\CreateRoomAction;
use App\Models\Room;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public string $playerName = '';
    public string $roomCode = '';
    public string $tab = 'join';

    public function createRoom(CreateRoomAction $action): void
    {
        $this->validate([
            'playerName' => 'required|min:2|max:20',
        ]);

        $room = $action->handle($this->playerName, session()->getId());

        session(['player_name' => $this->playerName]);

        $this->redirect(route('room', $room->code), navigate: true);
    }

    public function joinRoom(): void
    {
        $this->validate([
            'playerName' => 'required|min:2|max:20',
            'roomCode' => 'required',
        ]);

        $code = mb_strtoupper(trim($this->roomCode));

        $room = Room::query()
            ->where('code', $code)
            ->whereIn('status', ['waiting', 'active'])
            ->first();

        if (! $room) {
            $this->addError('roomCode', 'Room not found or has ended.');

            return;
        }

        session(['player_name' => $this->playerName]);

        $this->redirect(route('room', $room->code), navigate: true);
    }
};

?>

<div class="flex h-full flex-col">
    <div class="flex flex-1 items-center justify-center overflow-y-auto p-6">
        <div class="w-full max-w-sm">
        {{-- Logo / heading --}}
        <div class="mb-10 border-l-4 border-amber-400 pl-5">
            <p class="mb-2 font-mono text-[10px] tracking-[0.25em] text-zinc-400 dark:text-zinc-500">MULTIPLAYER</p>
            <h1 class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Pictionary</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Draw it. Guess it. Win it.</p>
        </div>

        {{-- Tab switcher --}}
        <div class="mb-6 flex border-b border-zinc-200 dark:border-zinc-800">
            <button
                wire:click="$set('tab', 'join')"
                class="{{ $tab === 'join' ? 'border-amber-400 text-zinc-900 dark:text-zinc-100' : 'border-transparent text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300' }} mr-6 border-b-2 pb-2 font-mono text-[10px] tracking-[0.2em] transition-colors"
            >
                JOIN ROOM
            </button>
            <button
                wire:click="$set('tab', 'create')"
                class="{{ $tab === 'create' ? 'border-amber-400 text-zinc-900 dark:text-zinc-100' : 'border-transparent text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300' }} border-b-2 pb-2 font-mono text-[10px] tracking-[0.2em] transition-colors"
            >
                CREATE ROOM
            </button>
        </div>

        @if ($tab === 'join')
            <form wire:submit="joinRoom" class="space-y-5">
                <div>
                    <label class="mb-2 block font-mono text-[10px] tracking-[0.2em] text-zinc-400 dark:text-zinc-500">YOUR NAME</label>
                    <input
                        wire:model="playerName"
                        type="text"
                        placeholder="e.g. Alice"
                        maxlength="20"
                        autofocus
                        class="w-full border-b border-zinc-300 bg-transparent py-2 text-zinc-900 placeholder-zinc-300 transition-colors outline-none focus:border-amber-400 dark:border-zinc-700 dark:text-zinc-100 dark:placeholder-zinc-600 dark:focus:border-amber-400"
                    />
                    @error('playerName')
                        <p class="mt-1.5 font-mono text-[10px] text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-2 block font-mono text-[10px] tracking-[0.2em] text-zinc-400 dark:text-zinc-500">ROOM CODE</label>
                    <input
                        wire:model="roomCode"
                        type="text"
                        placeholder="AB3K7"
                        maxlength="5"
                        class="w-full border-b border-zinc-300 bg-transparent py-2 font-mono text-2xl tracking-widest text-zinc-900 uppercase placeholder-zinc-300 transition-colors outline-none focus:border-amber-400 dark:border-zinc-700 dark:text-zinc-100 dark:placeholder-zinc-600 dark:focus:border-amber-400"
                    />
                    @error('roomCode')
                        <p class="mt-1.5 font-mono text-[10px] text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <button
                    type="submit"
                    class="mt-2 w-full border border-zinc-900 bg-zinc-900 py-3 font-mono text-[10px] tracking-[0.25em] text-white transition-colors hover:bg-zinc-800 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                    JOIN GAME
                </button>
            </form>
        @else
            <form wire:submit="createRoom" class="space-y-5">
                <div>
                    <label class="mb-2 block font-mono text-[10px] tracking-[0.2em] text-zinc-400 dark:text-zinc-500">YOUR NAME</label>
                    <input
                        wire:model="playerName"
                        type="text"
                        placeholder="e.g. Alice"
                        maxlength="20"
                        autofocus
                        class="w-full border-b border-zinc-300 bg-transparent py-2 text-zinc-900 placeholder-zinc-300 transition-colors outline-none focus:border-amber-400 dark:border-zinc-700 dark:text-zinc-100 dark:placeholder-zinc-600 dark:focus:border-amber-400"
                    />
                    @error('playerName')
                        <p class="mt-1.5 font-mono text-[10px] text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    You'll get a code to share with friends. The game starts when 2 players are in.
                </p>
                <button
                    type="submit"
                    class="mt-2 w-full border border-zinc-900 bg-zinc-900 py-3 font-mono text-[10px] tracking-[0.25em] text-white transition-colors hover:bg-zinc-800 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                    CREATE ROOM
                </button>
            </form>
        @endif
        </div>
    </div>

    {{-- Footer --}}
    <footer class="flex shrink-0 items-center justify-center border-t border-zinc-200 bg-white px-4 py-2 dark:border-zinc-800 dark:bg-zinc-900">
        <a
            href="https://github.com/joshcirre/pictionary"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-1.5 font-mono text-[10px] tracking-[0.2em] text-zinc-400 transition-colors hover:text-zinc-700 dark:text-zinc-500 dark:hover:text-zinc-300"
        >
            <svg class="size-3" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path d="M8 0C3.58 0 0 3.58 0 8a8 8 0 0 0 5.47 7.59c.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z" />
            </svg>
            GITHUB
        </a>
    </footer>
</div>
