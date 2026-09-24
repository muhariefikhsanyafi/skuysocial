<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_two_factor_authentications', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('secret')->nullable();
            $table->text('pending_secret')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('pending_expires_at')->nullable();
            $table->unsignedBigInteger('last_used_step')->nullable();
            $table->timestamps();
        });

        Schema::create('user_two_factor_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('user_two_factor_authentications', 'user_id')->cascadeOnDelete();
            $table->char('code_hash', 64);
            $table->timestamps();
            $table->unique(['user_id', 'code_hash']);
        });

        Schema::create('user_two_factor_login_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('challenge_digest', 64)->unique();
            $table->char('session_digest', 64);
            $table->unsignedBigInteger('auth_session_version');
            $table->boolean('remember')->default(false);
            $table->text('intended_url')->nullable();
            $table->string('source', 10)->default('web');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_two_factor_login_challenges');
        Schema::dropIfExists('user_two_factor_recovery_codes');
        Schema::dropIfExists('user_two_factor_authentications');
    }
};
