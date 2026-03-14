<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faculty_positions')) {
            Schema::create('faculty_positions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 120)->unique();
                $table->string('title', 160)->unique();
                $table->string('category', 80)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('faculty_profiles')) {
            Schema::create('faculty_profiles', function (Blueprint $table) {
                $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
                $table->string('employee_id', 80)->nullable()->unique();
                $table->string('department', 160)->nullable();
                $table->foreignId('position_id')->nullable()->constrained('faculty_positions')->nullOnDelete();
                $table->foreignId('schedule_teacher_id')->nullable()->unique()->constrained('schedule_teachers')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('schedule_teachers') && ! Schema::hasColumn('schedule_teachers', 'user_id')) {
            Schema::table('schedule_teachers', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('faculty_permission_overrides')) {
            Schema::create('faculty_permission_overrides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('permission_name', 150);
                $table->boolean('is_allowed')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'permission_name'], 'faculty_permission_override_unique');
            });
        }

        if (Schema::hasTable('faculty_profiles') && Schema::hasTable('portal_user_roles')) {
            $facultyUserIds = DB::table('portal_user_roles')
                ->where('role', 'faculty')
                ->pluck('user_id');

            foreach ($facultyUserIds as $userId) {
                DB::table('faculty_profiles')->updateOrInsert(
                    ['user_id' => (int) $userId],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        if (
            Schema::hasTable('schedule_teachers') &&
            Schema::hasTable('users') &&
            Schema::hasTable('faculty_profiles')
        ) {
            $teacherRows = DB::table('schedule_teachers')
                ->whereNull('user_id')
                ->whereNotNull('email')
                ->get(['id', 'email']);

            foreach ($teacherRows as $teacherRow) {
                $userId = DB::table('users')
                    ->whereRaw('LOWER(email) = ?', [strtolower((string) $teacherRow->email)])
                    ->value('id');

                if (! $userId) {
                    continue;
                }

                DB::table('schedule_teachers')
                    ->where('id', $teacherRow->id)
                    ->update([
                        'user_id' => (int) $userId,
                        'updated_at' => now(),
                    ]);

                DB::table('faculty_profiles')
                    ->where('user_id', (int) $userId)
                    ->whereNull('schedule_teacher_id')
                    ->update([
                        'schedule_teacher_id' => (int) $teacherRow->id,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_permission_overrides');

        if (Schema::hasColumn('schedule_teachers', 'user_id')) {
            Schema::table('schedule_teachers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        Schema::dropIfExists('faculty_profiles');
        Schema::dropIfExists('faculty_positions');
    }
};
