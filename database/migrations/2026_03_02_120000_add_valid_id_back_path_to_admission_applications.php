<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('admission_applications', 'valid_id_back_path')) {
                $table->string('valid_id_back_path')->nullable()->after('valid_id_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            if (Schema::hasColumn('admission_applications', 'valid_id_back_path')) {
                $table->dropColumn('valid_id_back_path');
            }
        });
    }
};

