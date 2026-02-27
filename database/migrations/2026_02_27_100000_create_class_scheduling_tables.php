<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_teachers')) {
            Schema::create('schedule_teachers', function (Blueprint $table) {
                $table->id();
                $table->string('employee_code', 60)->nullable()->unique();
                $table->string('full_name', 160);
                $table->string('email', 190)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('schedule_rooms')) {
            Schema::create('schedule_rooms', function (Blueprint $table) {
                $table->id();
                $table->string('building', 80)->nullable();
                $table->string('room_code', 80)->unique();
                $table->unsignedInteger('capacity')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('schedule_sections')) {
            Schema::create('schedule_sections', function (Blueprint $table) {
                $table->id();
                $table->string('program_name', 160);
                $table->unsignedTinyInteger('year_level')->default(1);
                $table->string('section_code', 80)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('class_schedule_items')) {
            Schema::create('class_schedule_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
                $table->foreignId('period_id')->nullable()->constrained('enrollment_periods')->nullOnDelete();
                $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('section_id')->nullable()->constrained('schedule_sections')->nullOnDelete();
                $table->foreignId('teacher_id')->nullable()->constrained('schedule_teachers')->nullOnDelete();
                $table->foreignId('room_id')->nullable()->constrained('schedule_rooms')->nullOnDelete();
                $table->enum('day_of_week', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);
                $table->time('start_time');
                $table->time('end_time');
                $table->string('schedule_text', 120)->nullable();
                $table->timestamps();

                $table->unique('enrollment_id', 'csi_enrollment_unique');
                $table->index(['period_id', 'day_of_week', 'room_id', 'start_time', 'end_time'], 'csi_room_overlap_idx');
                $table->index(['period_id', 'day_of_week', 'teacher_id', 'start_time', 'end_time'], 'csi_teacher_overlap_idx');
                $table->index(['period_id', 'day_of_week', 'section_id', 'start_time', 'end_time'], 'csi_section_overlap_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_items');
        Schema::dropIfExists('schedule_sections');
        Schema::dropIfExists('schedule_rooms');
        Schema::dropIfExists('schedule_teachers');
    }
};

