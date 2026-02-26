<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'program_key')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('program_key', 120)->default('GENERAL')->after('code');
            });
        }

        if (! Schema::hasColumn('courses', 'curriculum_version_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->unsignedBigInteger('curriculum_version_id')->nullable()->after('program_key');
            });
        }

        if (! Schema::hasColumn('courses', 'prerequisite_code')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('prerequisite_code')->nullable()->after('prerequisite_course_id');
            });
        }

        DB::table('courses')
            ->whereNull('program_key')
            ->update(['program_key' => 'GENERAL']);

        try {
            DB::statement('ALTER TABLE courses DROP INDEX courses_code_unique');
        } catch (\Throwable $e) {
            // Ignore if old unique index was already removed.
        }

        try {
            DB::statement('CREATE UNIQUE INDEX courses_program_key_code_unique ON courses (program_key, code)');
        } catch (\Throwable $e) {
            // Ignore if composite unique index already exists.
        }

        if (! Schema::hasTable('curriculum_versions')) {
            Schema::create('curriculum_versions', function (Blueprint $table) {
                $table->id();
                $table->string('program_key', 120);
                $table->string('program_name', 255);
                $table->string('label', 255);
                $table->string('effective_ay', 50)->nullable();
                $table->string('version', 50)->default('v1');
                $table->string('source_file_name')->nullable();
                $table->string('source_file_path')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(false);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['program_key', 'is_active'], 'cv_program_active_idx');
            });
        }

        if (! Schema::hasTable('curriculum_subjects')) {
            Schema::create('curriculum_subjects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('curriculum_version_id')->constrained('curriculum_versions')->cascadeOnDelete();
                $table->unsignedTinyInteger('year_level');
                $table->unsignedTinyInteger('semester');
                $table->string('code', 120);
                $table->string('title');
                $table->decimal('units', 5, 2)->default(3);
                $table->decimal('tf', 10, 2)->default(1500);
                $table->integer('lec')->default(3);
                $table->integer('lab')->default(0);
                $table->string('schedule')->nullable();
                $table->string('section')->nullable();
                $table->string('room')->nullable();
                $table->string('instructor')->nullable();
                $table->string('prerequisite_code', 120)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['curriculum_version_id', 'year_level', 'semester'], 'cs_version_term_idx');
                $table->index(['curriculum_version_id', 'code'], 'cs_version_code_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_subjects');
        Schema::dropIfExists('curriculum_versions');

        try {
            DB::statement('DROP INDEX courses_program_key_code_unique ON courses');
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            DB::statement('CREATE UNIQUE INDEX courses_code_unique ON courses (code)');
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'prerequisite_code')) {
                $table->dropColumn('prerequisite_code');
            }

            if (Schema::hasColumn('courses', 'curriculum_version_id')) {
                $table->dropColumn('curriculum_version_id');
            }

            if (Schema::hasColumn('courses', 'program_key')) {
                $table->dropColumn('program_key');
            }
        });
    }
};
