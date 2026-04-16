<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @use HasFactory<\Database\Factories\PlayerFactory>
 */
final class Player extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'join_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
