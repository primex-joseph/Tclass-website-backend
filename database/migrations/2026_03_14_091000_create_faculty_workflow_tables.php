<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faculty_assignments')) {
            Schema::create('faculty_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offering_id')->constrained('class_offerings')->cascadeOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->unsignedInteger('points')->default(100);
                $table->timestamp('due_at')->nullable();
                $table->boolean('is_published')->default(false);
                $table->timestamps();

                $table->index(['offering_id', 'is_published'], 'faculty_assignments_offering_published_idx');
            });
        }

        if (! Schema::hasTable('faculty_grade_entries')) {
            Schema::create('faculty_grade_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offering_id')->constrained('class_offerings')->cascadeOnDelete();
                $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
                $table->decimal('midterm_grade', 4, 2)->nullable();
                $table->decimal('final_grade', 4, 2)->nullable();
                $table->decimal('re_exam_grade', 4, 2)->nullable();
                $table->enum('status', ['draft', 'posted'])->default('draft');
                $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();

                $table->unique(['offering_id', 'enrollment_id'], 'faculty_grade_entries_unique');
            });
        }

        if (! Schema::hasTable('faculty_syllabi')) {
            Schema::create('faculty_syllabi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offering_id')->constrained('class_offerings')->cascadeOnDelete();
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('file_name', 190);
                $table->string('file_path', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();

                $table->unique('offering_id', 'faculty_syllabi_offering_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_syllabi');
        Schema::dropIfExists('faculty_grade_entries');
        Schema::dropIfExists('faculty_assignments');
    }
};
