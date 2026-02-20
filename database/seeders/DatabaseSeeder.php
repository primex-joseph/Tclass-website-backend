<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Keep existing admin account untouched. Seed only faculty/student dev accounts.
        $faculty = User::query()->updateOrCreate(
            ['email' => 'facultydev@tclass.local'],
            [
                'name' => 'Faculty Dev',
                'password' => Hash::make('Faculty123!'),
                'must_change_password' => false,
            ]
        );

        $student = User::query()->updateOrCreate(
            ['email' => 'studentdev@tclass.local'],
            [
                'name' => 'Student Dev',
                'student_number' => '25-1-1-1001',
                'password' => Hash::make('Student123!'),
                'must_change_password' => false,
            ]
        );

        if (Schema::hasTable('portal_user_roles')) {
            DB::table('portal_user_roles')->updateOrInsert(
                ['user_id' => $faculty->id, 'role' => 'faculty'],
                ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
            );

            DB::table('portal_user_roles')->updateOrInsert(
                ['user_id' => $student->id, 'role' => 'student'],
                ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
