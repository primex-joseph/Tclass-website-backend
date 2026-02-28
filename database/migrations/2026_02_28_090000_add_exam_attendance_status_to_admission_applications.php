<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->string('exam_attendance_status', 20)->default('not_attended')->after('exam_status');
        });

        DB::table('admission_applications')
            ->whereIn('exam_status', ['passed', 'failed'])
            ->update(['exam_attendance_status' => 'attended']);
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn('exam_attendance_status');
        });
    }
};
