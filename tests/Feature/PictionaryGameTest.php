<?php

declare(strict_types=1);

use App\Actions\CreateRoomAction;
use App\Actions\EndRoundAction;
use App\Actions\JoinRoomAction;
use App\Actions\StartRoundAction;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
    Bus::fake();
});

it('creates a room with a unique 5-char code', function () {
    $room = app(CreateRoomAction::class)->handle('Alice', 'session-alice');

    expect($room->code)->toHaveLength(5)
        ->and($room->status)->toBe('waiting')
        ->and($room->initiator_session_id)->toBe('session-alice');

    expect(Room::query()->where('code', $room->code)->count())->toBe(1);
    expect(Player::query()->where('room_id', $room->id)->count())->toBe(1);
});

it('joins a player to a room', function () {
    $room = app(CreateRoomAction::class)->handle('Alice', 'session-alice');
    $player = app(JoinRoomAction::class)->handle($room, 'Bob', 'session-bob');

    expect($player->name)->toBe('Bob')
        ->and($player->room_id)->toBe($room->id)
        ->and($player->is_active)->toBeTrue();

    expect($room->activePlayers()->count())->toBe(2);
});

it('starts a round when 2+ players are present', function () {
    $room = Room::factory()->create(['status' => 'waiting', 'current_drawer_order' => 0]);
    Player::factory()->create(['room_id' => $room->id, 'join_order' => 0, 'is_active' => true]);
    Player::factory()->create(['room_id' => $room->id, 'join_order' => 1, 'is_active' => true]);

    /** @var Round $round */
    $round = app(StartRoundAction::class)->handle($room);

    expect($round)->toBeInstanceOf(Round::class)
        ->and($round->status)->toBe('active')
        ->and($round->word)->not->toBeEmpty()
        ->and($round->ends_at)->not->toBeNull();

    $room->refresh();
    expect($room->status)->toBe('active');
});

it('does not start a round with only 1 player', function () {
    $room = Room::factory()->create(['status' => 'waiting']);
    Player::factory()->create(['room_id' => $room->id, 'join_order' => 0, 'is_active' => true]);

    $round = app(StartRoundAction::class)->handle($room);

    expect($round)->toBeNull();
});

it('ends a round with a correct guess and awards a point', function () {
    $room = Room::factory()->create(['status' => 'active', 'current_drawer_order' => 0]);
    $drawer = Player::factory()->create(['room_id' => $room->id, 'join_order' => 0, 'is_active' => true, 'score' => 0]);
    $guesser = Player::factory()->create(['room_id' => $room->id, 'join_order' => 1, 'is_active' => true, 'score' => 0]);

    $round = Round::factory()->create([
        'room_id' => $room->id,
        'drawer_player_id' => $drawer->id,
        'word' => 'elephant',
        'status' => 'active',
        'ends_at' => now()->addSeconds(30),
    ]);

    app(EndRoundAction::class)->handle($round, 'correct', $guesser);

    $round->refresh();
    $guesser->refresh();

    expect($round->status)->toBe('correct')
        ->and($round->winner_player_id)->toBe($guesser->id)
        ->and($guesser->score)->toBe(1);
});

it('ends a round on timeout without awarding points', function () {
    $room = Room::factory()->create(['status' => 'active', 'current_drawer_order' => 0]);
    $drawer = Player::factory()->create(['room_id' => $room->id, 'join_order' => 0, 'is_active' => true]);
    Player::factory()->create(['room_id' => $room->id, 'join_order' => 1, 'is_active' => true]);

    $round = Round::factory()->create([
        'room_id' => $room->id,
        'drawer_player_id' => $drawer->id,
        'word' => 'banana',
        'status' => 'active',
        'ends_at' => now()->subSeconds(1),
    ]);

    app(EndRoundAction::class)->handleTimeout($round);

    $round->refresh();
    expect($round->status)->toBe('timeout')
        ->and($round->winner_player_id)->toBeNull();
});

it('advances the drawer in round-robin order', function () {
    $room = Room::factory()->create(['status' => 'active', 'current_drawer_order' => 0]);
    $player1 = Player::factory()->create(['room_id' => $room->id, 'join_order' => 0, 'is_active' => true]);
    $player2 = Player::factory()->create(['room_id' => $room->id, 'join_order' => 1, 'is_active' => true]);

    $round1 = Round::factory()->create([
        'room_id' => $room->id,
        'drawer_player_id' => $player1->id,
        'word' => 'cat',
        'status' => 'active',
        'ends_at' => now()->addSeconds(30),
    ]);

    app(EndRoundAction::class)->handleTimeout($round1);

    $room->refresh();
    expect($room->current_drawer_order)->toBe(1);

    /** @var Round|null $round2 */
    $round2 = $room->currentRound()->first();
    expect($round2)->not->toBeNull()
        ->and($round2?->drawer_player_id)->toBe($player2->id);
});
