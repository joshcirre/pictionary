<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @use HasFactory<\Database\Factories\RoundFactory>
 */
final class Round extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function drawer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'drawer_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function secondsRemaining(): int
    {
        return max(0, (int) now()->diffInSeconds($this->ends_at, false));
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'strokes' => 'array',
        ];
    }
}
