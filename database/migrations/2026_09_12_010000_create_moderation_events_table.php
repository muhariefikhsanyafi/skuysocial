<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('target_user_id');
            $table->unsignedBigInteger('actor_user_id');
            $table->string('previous_state', 32);
            $table->enum('new_state', [
                'active',
                'restricted',
                'suspended',
                'banned',
            ]);
            $table->enum('reason_code', [
                'spam',
                'harassment',
                'impersonation',
                'platform_abuse',
                'security_abuse',
                'policy_violation',
                'other',
            ]);
            $table->text('reason_text');
            $table->timestamp('created_at');

            $table->index(
                ['target_user_id', 'created_at'],
                'moderation_events_target_created_at_index',
            );
            $table->index(
                ['actor_user_id', 'created_at'],
                'moderation_events_actor_created_at_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_events');
    }
};
