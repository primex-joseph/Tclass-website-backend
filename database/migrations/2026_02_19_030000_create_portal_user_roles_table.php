<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_user_roles')) {
            return;
        }

        Schema::create('portal_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['student', 'faculty', 'admin']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'role']);
            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_user_roles');
    }
};

