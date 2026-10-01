<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title_id', 160);
            $table->string('title_en', 160);
            $table->text('body_id');
            $table->text('body_en');
            $table->string('audience_type', 16)->default('all');
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('audience_cutoff_user_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'sent_at', 'id'], 'announcements_status_sent_at_id_index');
        });

        Schema::create('announcement_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id'], 'announcement_targets_announcement_id_user_id_unique');
            $table->index(['user_id', 'announcement_id'], 'announcement_targets_user_id_announcement_id_index');
        });

        Schema::create('announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id'], 'announcement_reads_announcement_id_user_id_unique');
            $table->index(['user_id', 'announcement_id'], 'announcement_reads_user_id_announcement_id_index');
        });

        Schema::create('announcement_push_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('batch_key', 191);
            $table->string('batch_type', 16);
            $table->string('locale', 8);
            $table->unsignedBigInteger('range_start_user_id')->nullable();
            $table->unsignedBigInteger('range_end_user_id')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('status', 16)->default('queued');
            $table->string('provider_outcome', 32)->nullable();
            $table->string('error_class', 160)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'batch_key'], 'announcement_push_batches_announcement_batch_unique');
            $table->index(['announcement_id', 'batch_type', 'status'], 'announcement_push_batches_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_push_batches');
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcement_targets');
        Schema::dropIfExists('announcements');
    }
};
