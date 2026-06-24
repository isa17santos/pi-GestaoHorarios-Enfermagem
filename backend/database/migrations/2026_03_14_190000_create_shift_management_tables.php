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
            $table->string('name', 255);
            $table->string('color', 7);
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('min_nurses')->default(0)->after('end_time');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('schedules')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'published', 'revision'])->default('draft')->after('end_date');
            $table->integer('edit_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['parent_id', 'status']);
        });

        Schema::create('nurse_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->boolean('prefers_morning')->default(false);
            $table->boolean('prefers_afternoon')->default(false);
            $table->boolean('prefers_night')->default(false);
            $table->boolean('avoid_weekends')->default(false);
            $table->boolean('prefers_weekends')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'month', 'year']);
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
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled']);
            $table->text('notes')->nullable();
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

        Schema::create('shift_swap_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('swap_request_id')->constrained('shift_swap_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['requester', 'target']);
            $table->timestamps();

            $table->unique(['swap_request_id', 'user_id', 'role']);
            $table->index(['swap_request_id', 'role']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('shift_swap_request_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('swap_request_id')->constrained('shift_swap_requests')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['offered', 'requested']);
            $table->timestamps();

            $table->unique(['swap_request_id', 'shift_id', 'type', 'owner_user_id']);
            $table->index(['swap_request_id', 'type']);
            $table->index(['shift_id', 'type']);
            $table->index(['owner_user_id', 'type']);
        });

        Schema::create('medical_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('medical_leave_replaced_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_leave_id')->constrained('medical_leaves')->cascadeOnDelete();
            $table->date('shift_date');
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('original_shift_id')->nullable()->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('original_shift_type_id')->constrained('shift_types')->cascadeOnDelete();
            $table->foreignId('temp_shift_id')->nullable()->constrained('shifts')->cascadeOnDelete();
            $table->boolean('was_shared')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_swap_request_shifts');
        Schema::dropIfExists('shift_swap_participants');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('user_shifts');
        Schema::dropIfExists('user_schedules');
        Schema::dropIfExists('shift_swap_requests');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('nurse_preferences');
        Schema::dropIfExists('shift_types');
        Schema::dropIfExists('medical_leave_replaced_shifts');
        Schema::dropIfExists('medical_leaves');
    }
};
