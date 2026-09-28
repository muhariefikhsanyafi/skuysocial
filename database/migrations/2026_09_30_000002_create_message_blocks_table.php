<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id'], 'msg_blocks_owner_target_unique');
            $table->index(['blocked_id', 'blocker_id'], 'msg_blocks_target_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_blocks');
    }
};
