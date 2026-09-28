<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(
                ['conversation_id', 'user_id'],
                'conversation_participants_conversation_id_user_id_unique',
            );
            $table->index(
                ['user_id', 'conversation_id'],
                'conversation_participants_user_id_conversation_id_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
