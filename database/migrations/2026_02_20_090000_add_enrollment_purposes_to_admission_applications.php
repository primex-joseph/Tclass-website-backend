<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->json('enrollment_purposes')->nullable()->after('contact_no');
            $table->string('enrollment_purpose_others')->nullable()->after('enrollment_purposes');
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn([
                'enrollment_purposes',
                'enrollment_purpose_others',
            ]);
        });
    }
};

