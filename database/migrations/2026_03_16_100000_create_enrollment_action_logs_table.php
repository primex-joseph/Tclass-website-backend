<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('enrollment_action_logs')) {
            Schema::create('enrollment_action_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
                $table->foreignId('offering_id')->nullable()->constrained('class_offerings')->nullOnDelete();
                $table->foreignId('student_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('acted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('action', ['add', 'verify', 'unverify', 'remove']);
                $table->enum('from_status', ['draft', 'unofficial', 'official', 'rejected', 'dropped'])->nullable();
                $table->enum('to_status', ['draft', 'unofficial', 'official', 'rejected', 'dropped'])->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->timestamps();

                $table->index(['offering_id', 'acted_at'], 'enrollment_action_logs_offering_acted_idx');
                $table->index(['student_user_id', 'acted_at'], 'enrollment_action_logs_student_acted_idx');
                $table->index(['enrollment_id', 'acted_at'], 'enrollment_action_logs_enrollment_acted_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_action_logs');
    }
};
