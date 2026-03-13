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
        $admin = User::query()->updateOrCreate(
            ['email' => 'admindev@tclass.local'],
            [
                'name' => 'Admin Dev',
                'password' => Hash::make('Admin123!'),
                'must_change_password' => false,
            ]
        );

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
                ['user_id' => $admin->id, 'role' => 'admin'],
                ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
            );

            DB::table('portal_user_roles')->updateOrInsert(
                ['user_id' => $faculty->id, 'role' => 'faculty'],
                ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
            );

            DB::table('portal_user_roles')->updateOrInsert(
                ['user_id' => $student->id, 'role' => 'student'],
                ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        if (Schema::hasTable('admission_applications')) {
            DB::table('admission_applications')->updateOrInsert(
                ['email' => $student->email, 'status' => 'approved'],
                [
                    'full_name' => $student->name,
                    'age' => 20,
                    'gender' => 'Male',
                    'primary_course' => 'BS Information Technology',
                    'secondary_course' => null,
                    'application_type' => 'admission',
                    'remarks' => 'Seeded dev account for BSIT testing.',
                    'approved_at' => now(),
                    'processed_by' => null,
                    'created_user_id' => $student->id,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('schedule_sections')) {
            foreach ([1, 2, 3, 4] as $year) {
                foreach (['A', 'B', 'C'] as $suffix) {
                    $code = "BSIT-{$year}{$suffix}";
                    DB::table('schedule_sections')->updateOrInsert(
                        ['section_code' => $code],
                        [
                            'program_name' => 'BS Information Technology',
                            'year_level' => $year,
                            'is_active' => 1,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }

        if (Schema::hasTable('schedule_rooms')) {
            DB::table('schedule_rooms')->updateOrInsert(
                ['room_code' => 'L306A'],
                ['building' => 'L', 'capacity' => 45, 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
            DB::table('schedule_rooms')->updateOrInsert(
                ['room_code' => 'L206'],
                ['building' => 'L', 'capacity' => 40, 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (Schema::hasTable('schedule_teachers')) {
            DB::table('schedule_teachers')->updateOrInsert(
                ['employee_code' => 'EMP-1001'],
                ['full_name' => 'Prof. Dela Cruz', 'email' => 'delacruz@tclass.edu', 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
            DB::table('schedule_teachers')->updateOrInsert(
                ['employee_code' => 'EMP-1002'],
                ['full_name' => 'Prof. Santos', 'email' => 'santos@tclass.edu', 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (
            Schema::hasTable('class_offerings') &&
            Schema::hasTable('enrollment_periods') &&
            Schema::hasTable('courses') &&
            Schema::hasTable('schedule_sections') &&
            Schema::hasTable('schedule_teachers') &&
            Schema::hasTable('schedule_rooms')
        ) {
            $periodId = DB::table('enrollment_periods')->where('is_active', 1)->value('id');
            $sectionId = DB::table('schedule_sections')->where('section_code', 'BSIT-4A')->value('id');
            $teacherA = DB::table('schedule_teachers')->where('employee_code', 'EMP-1001')->value('id');
            $teacherB = DB::table('schedule_teachers')->where('employee_code', 'EMP-1002')->value('id');
            $roomA = DB::table('schedule_rooms')->where('room_code', 'L306A')->value('id');
            $roomB = DB::table('schedule_rooms')->where('room_code', 'L206')->value('id');

            $courseCodes = ['THEO1', 'THEO2', 'ORIENT1', 'ORIENT2', 'IT64', 'FIL1'];
            if ($periodId && $sectionId && $teacherA && $teacherB && $roomA && $roomB) {
                $baseRows = [
                    ['code' => 'THEO1', 'day' => 'Mon', 'start' => '08:30', 'end' => '09:30', 'teacher_id' => $teacherA, 'room_id' => $roomA],
                    ['code' => 'THEO2', 'day' => 'Mon', 'start' => '09:30', 'end' => '10:30', 'teacher_id' => $teacherB, 'room_id' => $roomB],
                    ['code' => 'ORIENT1', 'day' => 'Tue', 'start' => '08:30', 'end' => '09:30', 'teacher_id' => $teacherA, 'room_id' => $roomA],
                    ['code' => 'ORIENT2', 'day' => 'Tue', 'start' => '09:30', 'end' => '10:30', 'teacher_id' => $teacherB, 'room_id' => $roomB],
                    ['code' => 'IT64', 'day' => 'Wed', 'start' => '08:30', 'end' => '10:00', 'teacher_id' => $teacherA, 'room_id' => $roomA],
                    ['code' => 'FIL1', 'day' => 'Thu', 'start' => '08:30', 'end' => '10:00', 'teacher_id' => $teacherB, 'room_id' => $roomB],
                ];

                foreach ($baseRows as $row) {
                    $courseId = DB::table('courses')
                        ->where('code', $row['code'])
                        ->where('program_key', 'BS_INFORMATION_TECHNOLOGY')
                        ->value('id');
                    if (! $courseId) {
                        continue;
                    }
                    $startLabel = date('h:i A', strtotime("1970-01-01 {$row['start']}"));
                    $endLabel = date('h:i A', strtotime("1970-01-01 {$row['end']}"));
                    DB::table('class_offerings')->updateOrInsert(
                        [
                            'period_id' => $periodId,
                            'course_id' => $courseId,
                            'section_id' => $sectionId,
                            'day_of_week' => $row['day'],
                            'start_time' => $row['start'] . ':00',
                            'end_time' => $row['end'] . ':00',
                        ],
                        [
                            'teacher_id' => $row['teacher_id'],
                            'room_id' => $row['room_id'],
                            'schedule_text' => "{$row['day']} {$startLabel} - {$endLabel}",
                            'capacity' => 40,
                            'is_active' => 1,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
