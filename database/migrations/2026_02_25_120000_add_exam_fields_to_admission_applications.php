<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->string('exam_status', 20)->default('not_attended')->after('status');
            $table->timestamp('exam_schedule_sent_at')->nullable()->after('exam_status');
            $table->json('exam_schedule_payload')->nullable()->after('exam_schedule_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn([
                'exam_status',
                'exam_schedule_sent_at',
                'exam_schedule_payload',
            ]);
        });
    }
};
