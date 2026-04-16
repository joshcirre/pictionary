<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @use HasFactory<\Database\Factories\RoomFactory>
 */
final class Room extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    /** @return HasMany<Player, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class)->orderBy('join_order');
    }

    /** @return HasMany<Player, $this> */
    public function activePlayers(): HasMany
    {
        return $this->hasMany(Player::class)->where('is_active', true)->orderBy('join_order');
    }

    /** @return HasMany<Round, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('id');
    }

    /** @return HasOne<Round, $this> */
    public function currentRound(): HasOne
    {
        return $this->hasOne(Round::class)->where('status', 'active')->latestOfMany();
    }

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }

    public function currentDrawer(): ?Player
    {
        return $this->activePlayers()->skip($this->current_drawer_order % max(1, $this->activePlayers()->count()))->first();
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'current_drawer_order' => 'integer',
        ];
    }
}
