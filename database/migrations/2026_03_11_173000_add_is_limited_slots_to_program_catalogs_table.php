<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_catalogs', function (Blueprint $table) {
            if (! Schema::hasColumn('program_catalogs', 'is_limited_slots')) {
                $table->boolean('is_limited_slots')->default(false)->after('sort_order');
            }
        });

        DB::table('program_catalogs')
            ->where(function ($query) {
                $query->where('badge_text', 'like', '%limited%')
                    ->orWhere('badge_text', 'like', '%slots left%');
            })
            ->update(['is_limited_slots' => true]);

        DB::table('program_catalogs')
            ->where('is_active', false)
            ->update(['badge_text' => 'Inactive']);

        DB::table('program_catalogs')
            ->where('is_active', true)
            ->where('is_limited_slots', true)
            ->update(['badge_text' => 'Limited slots']);

        DB::table('program_catalogs')
            ->where('is_active', true)
            ->where('is_limited_slots', false)
            ->update(['badge_text' => 'Active']);
    }

    public function down(): void
    {
        Schema::table('program_catalogs', function (Blueprint $table) {
            if (Schema::hasColumn('program_catalogs', 'is_limited_slots')) {
                $table->dropColumn('is_limited_slots');
            }
        });
    }
};
