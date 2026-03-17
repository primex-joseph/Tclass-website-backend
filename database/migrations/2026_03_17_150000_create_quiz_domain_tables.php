<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quizzes')) {
            Schema::create('quizzes', function (Blueprint $table) {
                $table->id();
                $table->enum('scope', ['admin', 'faculty']);
                $table->string('title', 180);
                $table->text('instructions')->nullable();
                $table->unsignedTinyInteger('pass_rate')->default(50);
                $table->unsignedSmallInteger('duration_minutes')->default(30);
                $table->enum('status', ['draft', 'published'])->default('draft');
                $table->enum('quiz_type', ['regular', 'entrance'])->default('regular');
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('offering_id')->nullable()->constrained('class_offerings')->nullOnDelete();
                $table->foreignId('course_program_id')->nullable()->constrained('program_catalogs')->nullOnDelete();
                $table->boolean('shuffle_items')->default(true);
                $table->boolean('shuffle_choices')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('share_token', 120)->nullable()->unique();
                $table->json('invited_admission_ids')->nullable();
                $table->json('invited_recipient_emails')->nullable();
                $table->timestamps();

                $table->index(['scope', 'quiz_type', 'status'], 'quizzes_scope_type_status_idx');
                $table->index(['scope', 'created_by_user_id'], 'quizzes_scope_creator_idx');
            });
        }

        if (! Schema::hasTable('quiz_items')) {
            Schema::create('quiz_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
                $table->text('prompt');
                $table->json('choices');
                $table->string('correct_choice_id', 20);
                $table->unsignedInteger('display_order')->default(1);
                $table->timestamps();

                $table->index(['quiz_id', 'display_order'], 'quiz_items_order_idx');
            });
        }

        if (! Schema::hasTable('quiz_attempts')) {
            Schema::create('quiz_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
                $table->foreignId('student_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('student_admission_id')->nullable()->constrained('admission_applications')->nullOnDelete();
                $table->string('student_name', 180);
                $table->string('student_email', 190)->nullable();
                $table->dateTime('started_at');
                $table->dateTime('ends_at');
                $table->dateTime('submitted_at')->nullable();
                $table->boolean('auto_submitted')->default(false);
                $table->unsignedInteger('score')->nullable();
                $table->unsignedInteger('total')->nullable();
                $table->unsignedInteger('correct_count')->nullable();
                $table->unsignedInteger('wrong_count')->nullable();
                $table->boolean('passed')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['quiz_id', 'submitted_at'], 'quiz_attempts_quiz_submitted_idx');
                $table->index(['student_user_id', 'quiz_id'], 'quiz_attempts_student_quiz_idx');
            });
        }

        if (! Schema::hasTable('quiz_attempt_answers')) {
            Schema::create('quiz_attempt_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
                $table->foreignId('quiz_item_id')->constrained('quiz_items')->cascadeOnDelete();
                $table->string('selected_choice_id', 20)->nullable();
                $table->string('selected_choice_text', 500)->nullable();
                $table->string('correct_choice_id', 20);
                $table->string('correct_choice_text', 500)->nullable();
                $table->boolean('is_correct')->default(false);
                $table->timestamps();

                $table->unique(['attempt_id', 'quiz_item_id'], 'quiz_attempt_answers_unique_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_items');
        Schema::dropIfExists('quizzes');
    }
};
