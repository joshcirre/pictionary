<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 6)->unique();
            $table->string('initiator_session_id');
            $table->enum('status', ['waiting', 'active', 'ended'])->default('waiting');
            $table->unsignedInteger('current_drawer_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
