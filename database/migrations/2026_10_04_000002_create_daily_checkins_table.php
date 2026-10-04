<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checkin_date');
            $table->timestamps();
            $table->unique(
                ['user_id', 'checkin_date'],
                'daily_checkins_user_date_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checkins');
    }
};
