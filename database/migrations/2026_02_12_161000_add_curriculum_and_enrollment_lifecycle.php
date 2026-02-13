<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'year_level')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->unsignedTinyInteger('year_level')->default(1)->after('instructor');
                $table->unsignedTinyInteger('semester')->default(1)->after('year_level');
                $table->foreignId('prerequisite_course_id')->nullable()->after('semester')->constrained('courses')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('student_course_results')) {
            Schema::create('student_course_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->decimal('grade', 4, 2)->nullable();
                $table->enum('status', ['passed', 'failed', 'incomplete'])->default('incomplete');
                $table->timestamps();

                $table->unique(['user_id', 'course_id']);
            });
        }

        DB::statement("ALTER TABLE enrollments MODIFY status ENUM('draft','pending','approved','unofficial','official','rejected','dropped') NOT NULL DEFAULT 'draft'");
        DB::table('enrollments')->where('status', 'approved')->update(['status' => 'official']);
        DB::table('enrollments')->where('status', 'pending')->update(['status' => 'unofficial']);
        DB::statement("ALTER TABLE enrollments MODIFY status ENUM('draft','unofficial','official','rejected','dropped') NOT NULL DEFAULT 'draft'");

        // Demo curriculum structure
        DB::table('courses')->where('code', 'COMP101')->update(['year_level' => 1, 'semester' => 1]);
        DB::table('courses')->where('code', 'ENG150')->update(['year_level' => 1, 'semester' => 1]);
        DB::table('courses')->where('code', 'MATH201')->update(['year_level' => 1, 'semester' => 2]);

        // Set prerequisite: MATH201 requires COMP101
        $comp = DB::table('courses')->where('code', 'COMP101')->first();
        if ($comp) {
            DB::table('courses')->where('code', 'MATH201')->update(['prerequisite_course_id' => $comp->id]);
        }

        // Seed passed results for dev student to demonstrate "next sem auto"
        $student = DB::table('users')->where('email', 'studentdev@tclass.local')->first();
        $eng = DB::table('courses')->where('code', 'ENG150')->first();
        if ($student && $comp && $eng) {
            DB::table('student_course_results')->updateOrInsert(
                ['user_id' => $student->id, 'course_id' => $comp->id],
                ['grade' => 1.75, 'status' => 'passed', 'updated_at' => now(), 'created_at' => now()]
            );
            DB::table('student_course_results')->updateOrInsert(
                ['user_id' => $student->id, 'course_id' => $eng->id],
                ['grade' => 2.00, 'status' => 'passed', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE enrollments MODIFY status ENUM('draft','pending','approved','rejected','dropped') NOT NULL DEFAULT 'draft'");
        DB::table('enrollments')->where('status', 'official')->update(['status' => 'approved']);
        DB::table('enrollments')->where('status', 'unofficial')->update(['status' => 'pending']);

        Schema::dropIfExists('student_course_results');

        if (Schema::hasColumn('courses', 'year_level')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('prerequisite_course_id');
                $table->dropColumn(['year_level', 'semester']);
            });
        }
    }
};
