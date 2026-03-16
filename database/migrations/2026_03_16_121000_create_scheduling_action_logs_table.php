<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduling_action_logs')) {
            Schema::create('scheduling_action_logs', function (Blueprint $table) {
                $table->id();
                $table->enum('entity_type', ['room', 'offering']);
                $table->unsignedBigInteger('entity_id');
                $table->string('action', 60);
                $table->foreignId('acted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('room_id')->nullable()->constrained('schedule_rooms')->nullOnDelete();
                $table->foreignId('offering_id')->nullable()->constrained('class_offerings')->nullOnDelete();
                $table->foreignId('period_id')->nullable()->constrained('enrollment_periods')->nullOnDelete();
                $table->json('before_snapshot')->nullable();
                $table->json('after_snapshot')->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->timestamps();

                $table->index(['entity_type', 'entity_id', 'id'], 'sched_log_entity_idx');
                $table->index(['room_id', 'id'], 'sched_log_room_idx');
                $table->index(['offering_id', 'id'], 'sched_log_offering_idx');
                $table->index(['period_id', 'id'], 'sched_log_period_idx');
                $table->index(['acted_by_user_id', 'id'], 'sched_log_actor_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_action_logs');
    }
};

