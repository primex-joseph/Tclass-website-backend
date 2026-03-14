<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\FacultyWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class PortalUsersSeeder extends Seeder
{
    /**
     * @var array<int, array{
     *   role: string,
     *   name: string,
     *   email: string,
     *   password: string,
     *   student_number?: string|null,
     *   faculty_profile?: array{
     *     employee_id?: string|null,
     *     department?: string|null,
     *     position_code?: string|null
     *   }|null
     * }>
     */
    private const USERS = [
        [
            'role' => 'admin',
            'name' => 'Admin Dev',
            'email' => 'admindev@tclass.local',
            'password' => 'Admin123!',
        ],
        [
            'role' => 'admin',
            'name' => 'Admin Support',
            'email' => 'adminsupport@tclass.local',
            'password' => 'AdminSupport123!',
        ],
        [
            'role' => 'faculty',
            'name' => 'Faculty Dev',
            'email' => 'facultydev@tclass.local',
            'password' => 'Faculty123!',
            'faculty_profile' => [
                'employee_id' => '19-00123',
                'department' => 'College of Information Technology',
                'position_code' => 'instructor_i',
            ],
        ],
        [
            'role' => 'faculty',
            'name' => 'Registrar Faculty',
            'email' => 'registrar.faculty@tclass.local',
            'password' => 'Registrar123!',
            'faculty_profile' => [
                'employee_id' => 'REG-0001',
                'department' => 'Office of the Registrar',
                'position_code' => 'registrar',
            ],
        ],
        [
            'role' => 'student',
            'name' => 'Student Dev',
            'email' => 'studentdev@tclass.local',
            'password' => 'Student123!',
            'student_number' => '25-1-1-1001',
        ],
        [
            'role' => 'student',
            'name' => 'Faculty Student One',
            'email' => 'facultystudent1@tclass.local',
            'password' => 'Student123!',
            'student_number' => '25-1-1-1002',
        ],
        [
            'role' => 'student',
            'name' => 'Faculty Student Two',
            'email' => 'facultystudent2@tclass.local',
            'password' => 'Student123!',
            'student_number' => '25-1-1-1003',
        ],
    ];

    public function run(): void
    {
        $positionByCode = collect();

        if (Schema::hasTable('faculty_positions')) {
            $positionByCode = DB::table('faculty_positions')->pluck('id', 'code');
        }

        FacultyWorkflow::syncPermissionCatalog();

        foreach (self::USERS as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'student_number' => $row['student_number'] ?? null,
                    'password' => Hash::make($row['password']),
                    'must_change_password' => false,
                ]
            );

            if (Schema::hasTable('portal_user_roles')) {
                DB::table('portal_user_roles')->updateOrInsert(
                    ['user_id' => $user->id, 'role' => $row['role']],
                    ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            if ($row['role'] === 'student') {
                $this->seedAdmissionRecord($user);
                continue;
            }

            if ($row['role'] !== 'faculty' || ! Schema::hasTable('faculty_profiles')) {
                continue;
            }

            $profile = $row['faculty_profile'] ?? [];
            $positionId = null;
            $positionCode = (string) ($profile['position_code'] ?? '');
            if ($positionCode !== '' && $positionByCode->has($positionCode)) {
                $positionId = (int) $positionByCode->get($positionCode);
            }

            DB::table('faculty_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'employee_id' => $profile['employee_id'] ?? null,
                    'department' => $profile['department'] ?? null,
                    'position_id' => $positionId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $template = FacultyWorkflow::templateFromPosition((string) ($profile['position_code'] ?? ''));
            FacultyWorkflow::assignTemplate($user, $template);
        }

        $this->printCredentials();
    }

    private function seedAdmissionRecord(User $student): void
    {
        if (! Schema::hasTable('admission_applications')) {
            return;
        }

        DB::table('admission_applications')->updateOrInsert(
            ['email' => $student->email, 'status' => 'approved'],
            [
                'full_name' => $student->name,
                'age' => 20,
                'gender' => 'Male',
                'primary_course' => 'BS Information Technology',
                'secondary_course' => null,
                'application_type' => 'admission',
                'remarks' => 'Seeded user account.',
                'approved_at' => now(),
                'processed_by' => null,
                'created_user_id' => $student->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function printCredentials(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Seeded portal credentials:');
        foreach (self::USERS as $row) {
            $this->command->line(sprintf(
                '- [%s] %s / %s',
                strtoupper($row['role']),
                $row['email'],
                $row['password']
            ));
        }
        $this->command->newLine();
    }
}

