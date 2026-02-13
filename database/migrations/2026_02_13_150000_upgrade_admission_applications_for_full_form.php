<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->string('facebook_account')->nullable()->after('email');
            $table->string('contact_no')->nullable()->after('facebook_account');
            $table->json('form_data')->nullable()->after('contact_no');
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn(['facebook_account', 'contact_no', 'form_data']);
        });
    }
};
