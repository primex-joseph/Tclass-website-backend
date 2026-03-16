<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_rooms')) {
            Schema::table('schedule_rooms', function (Blueprint $table) {
                if (! Schema::hasColumn('schedule_rooms', 'title')) {
                    $table->string('title', 140)->nullable()->after('room_code');
                }
                if (! Schema::hasColumn('schedule_rooms', 'description')) {
                    $table->text('description')->nullable()->after('title');
                }
                if (! Schema::hasColumn('schedule_rooms', 'icon_key')) {
                    $table->string('icon_key', 80)->nullable()->after('description');
                }
                if (! Schema::hasColumn('schedule_rooms', 'created_by_user_id')) {
                    $table->foreignId('created_by_user_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('schedule_rooms', 'updated_by_user_id')) {
                    $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('class_offerings')) {
            Schema::table('class_offerings', function (Blueprint $table) {
                if (! Schema::hasColumn('class_offerings', 'created_by_user_id')) {
                    $table->foreignId('created_by_user_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('class_offerings', 'updated_by_user_id')) {
                    $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('class_offerings')) {
            Schema::table('class_offerings', function (Blueprint $table) {
                if (Schema::hasColumn('class_offerings', 'updated_by_user_id')) {
                    $table->dropConstrainedForeignId('updated_by_user_id');
                }
                if (Schema::hasColumn('class_offerings', 'created_by_user_id')) {
                    $table->dropConstrainedForeignId('created_by_user_id');
                }
            });
        }

        if (Schema::hasTable('schedule_rooms')) {
            Schema::table('schedule_rooms', function (Blueprint $table) {
                if (Schema::hasColumn('schedule_rooms', 'updated_by_user_id')) {
                    $table->dropConstrainedForeignId('updated_by_user_id');
                }
                if (Schema::hasColumn('schedule_rooms', 'created_by_user_id')) {
                    $table->dropConstrainedForeignId('created_by_user_id');
                }
                if (Schema::hasColumn('schedule_rooms', 'icon_key')) {
                    $table->dropColumn('icon_key');
                }
                if (Schema::hasColumn('schedule_rooms', 'description')) {
                    $table->dropColumn('description');
                }
                if (Schema::hasColumn('schedule_rooms', 'title')) {
                    $table->dropColumn('title');
                }
            });
        }
    }
};

