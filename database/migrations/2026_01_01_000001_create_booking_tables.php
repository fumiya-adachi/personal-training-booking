<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('client')->index();
        });
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('specialty', 120)->default('');
            $table->text('bio')->nullable();
            $table->string('color', 7)->default('#D7B9A4');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->time('open_time')->default('09:00:00');
            $table->time('close_time')->default('19:00:00');
            $table->boolean('is_open')->default(true);
            $table->timestamps();
        });
        Schema::create('trainer_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time')->default('09:00:00');
            $table->time('end_time')->default('19:00:00');
            $table->boolean('is_working')->default(true);
            $table->timestamps();
            $table->unique(['trainer_id', 'day_of_week']);
        });
        Schema::create('closed_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 255)->default('休業日');
            $table->timestamps();
        });
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('confirmed');
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('duration')->default(60);
            $table->unsignedInteger('price')->default(8800);
            $table->timestamps();
            $table->index(['trainer_id', 'status', 'starts_at', 'ends_at'], 'reservations_trainer_availability');
            $table->index(['user_id', 'status', 'starts_at', 'ends_at'], 'reservations_user_availability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('closed_dates');
        Schema::dropIfExists('trainer_schedules');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('trainers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
