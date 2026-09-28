<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            // This is a historical ID boundary, not a live relation. A foreign key with
            // SET NULL would rewind read state if a message were removed and revive old unread messages.
            $table->unsignedBigInteger('last_read_message_id')
                ->nullable()
                ->index('conversation_participants_last_read_message_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropIndex('conversation_participants_last_read_message_id_index');
            $table->dropColumn('last_read_message_id');
        });
    }
};
