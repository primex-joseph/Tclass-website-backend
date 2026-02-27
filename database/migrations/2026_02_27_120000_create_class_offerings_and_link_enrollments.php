<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_offerings')) {
            Schema::create('class_offerings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('period_id')->constrained('enrollment_periods')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('section_id')->constrained('schedule_sections')->cascadeOnDelete();
                $table->foreignId('teacher_id')->constrained('schedule_teachers')->cascadeOnDelete();
                $table->foreignId('room_id')->constrained('schedule_rooms')->cascadeOnDelete();
                $table->enum('day_of_week', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);
                $table->time('start_time');
                $table->time('end_time');
                $table->string('schedule_text', 120)->nullable();
                $table->unsignedInteger('capacity')->default(40);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['period_id', 'day_of_week', 'room_id', 'start_time', 'end_time'], 'offer_room_overlap_idx');
                $table->index(['period_id', 'day_of_week', 'teacher_id', 'start_time', 'end_time'], 'offer_teacher_overlap_idx');
                $table->index(['period_id', 'day_of_week', 'section_id', 'start_time', 'end_time'], 'offer_section_overlap_idx');
                $table->index(['period_id', 'course_id'], 'offer_period_course_idx');
            });
        }

        if (! Schema::hasColumn('enrollments', 'offering_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->foreignId('offering_id')->nullable()->after('period_id')->constrained('class_offerings')->nullOnDelete();
                $table->index(['period_id', 'offering_id'], 'enroll_period_offering_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('enrollments', 'offering_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('offering_id');
            });
        }
        Schema::dropIfExists('class_offerings');
    }
};

