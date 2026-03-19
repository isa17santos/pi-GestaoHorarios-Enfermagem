<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shift_types', function (Blueprint $table) {
            $table->id();
            $table->enum('name', ['morning', 'afternoon', 'night', 'holidays', 'sick leave', 'parental leave']);
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('nurse_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->boolean('prefers_morning')->default(false);
            $table->boolean('prefers_afternoon')->default(false);
            $table->boolean('prefers_night')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body');
            $table->boolean('read')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('shift_type_id')->constrained('shift_types')->restrictOnDelete();
            $table->date('shift_date');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_schedules', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'schedule_id']);
        });

        Schema::create('user_shifts', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'shift_id']);
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'notification_id']);
        });

        Schema::create('user_swaps', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('swap_id')->constrained('shift_swap_requests')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'swap_id']);
        });

        Schema::create('swap_shifts', function (Blueprint $table) {
            $table->foreignId('swap_id')->constrained('shift_swap_requests')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['swap_id', 'shift_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('swap_shifts');
        Schema::dropIfExists('user_swaps');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('user_shifts');
        Schema::dropIfExists('user_schedules');
        Schema::dropIfExists('shift_swap_requests');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('nurse_preferences');
        Schema::dropIfExists('shift_types');
    }
};
