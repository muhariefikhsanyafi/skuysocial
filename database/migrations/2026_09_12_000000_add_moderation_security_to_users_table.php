<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('moderation_state', [
                'active',
                'restricted',
                'suspended',
                'banned',
            ])->default('active')->index();
            $table->timestamp('moderation_state_changed_at')->nullable();
            $table->unsignedBigInteger('auth_session_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['moderation_state']);
            $table->dropColumn([
                'moderation_state',
                'moderation_state_changed_at',
                'auth_session_version',
            ]);
        });
    }
};
