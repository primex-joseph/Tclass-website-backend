<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        if (! Schema::hasColumn('courses', 'units')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->decimal('units', 5, 2)->default(3)->after('description');
                $table->decimal('tf', 10, 2)->default(1500)->after('units');
                $table->integer('lec')->default(3)->after('tf');
                $table->integer('lab')->default(0)->after('lec');
                $table->string('schedule')->nullable()->after('lab');
                $table->string('section')->nullable()->after('schedule');
                $table->string('room')->nullable()->after('section');
                $table->string('instructor')->nullable()->after('room');
            });
        }

        if (! Schema::hasColumn('enrollments', 'period_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->foreignId('period_id')->nullable()->after('course_id')->constrained('enrollment_periods')->nullOnDelete();
                $table->timestamp('assessed_at')->nullable()->after('requested_at');
            });
        }

        DB::statement("ALTER TABLE enrollments MODIFY status ENUM('draft','pending','approved','rejected','dropped') NOT NULL DEFAULT 'draft'");

        DB::table('enrollment_periods')->insert([
            ['name' => '1st Semester AY 2026-2027', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '2nd Semester AY 2026-2027', 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Summer AY 2026-2027', 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('courses')->whereNull('schedule')->update([
            'units' => 3,
            'tf' => 1500,
            'lec' => 3,
            'lab' => 0,
            'schedule' => 'MWF 8:00-9:00 AM',
            'section' => 'A',
            'room' => 'Rm 301',
            'instructor' => 'Prof. Santos',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE enrollments MODIFY status ENUM('pending','approved','rejected','dropped') NOT NULL DEFAULT 'pending'");

        if (Schema::hasColumn('enrollments', 'period_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('period_id');
                $table->dropColumn('assessed_at');
            });
        }

        if (Schema::hasColumn('courses', 'units')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn(['units', 'tf', 'lec', 'lab', 'schedule', 'section', 'room', 'instructor']);
            });
        }

        Schema::dropIfExists('enrollment_periods');
    }
};
