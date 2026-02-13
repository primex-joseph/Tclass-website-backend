<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->string('id_picture_path')->nullable()->after('form_data');
            $table->string('one_by_one_picture_path')->nullable()->after('id_picture_path');
            $table->string('right_thumbmark_path')->nullable()->after('one_by_one_picture_path');
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn([
                'id_picture_path',
                'one_by_one_picture_path',
                'right_thumbmark_path',
            ]);
        });
    }
};
