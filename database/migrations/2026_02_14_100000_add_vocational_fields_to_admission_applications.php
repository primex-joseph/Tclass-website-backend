<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->string('application_type', 20)->default('admission')->after('email');
            $table->string('valid_id_type')->nullable()->after('application_type');
            $table->string('birth_certificate_path')->nullable()->after('right_thumbmark_path');
            $table->string('valid_id_path')->nullable()->after('birth_certificate_path');
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn([
                'application_type',
                'valid_id_type',
                'birth_certificate_path',
                'valid_id_path',
            ]);
        });
    }
};
