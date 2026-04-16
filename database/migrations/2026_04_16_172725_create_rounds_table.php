<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawer_player_id')->constrained('players')->cascadeOnDelete();
            $table->string('word');
            $table->enum('status', ['active', 'correct', 'timeout'])->default('active');
            $table->foreignId('winner_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
