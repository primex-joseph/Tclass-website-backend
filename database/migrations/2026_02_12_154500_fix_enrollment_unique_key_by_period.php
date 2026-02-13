<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE enrollments ADD INDEX enrollments_user_id_index (user_id)');
        DB::statement('ALTER TABLE enrollments DROP INDEX enrollments_user_id_course_id_unique');
        DB::statement('ALTER TABLE enrollments ADD UNIQUE KEY enrollments_user_course_period_unique (user_id, course_id, period_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE enrollments DROP INDEX enrollments_user_course_period_unique');
        DB::statement('ALTER TABLE enrollments ADD UNIQUE KEY enrollments_user_id_course_id_unique (user_id, course_id)');
        DB::statement('ALTER TABLE enrollments DROP INDEX enrollments_user_id_index');
    }
};
