<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PortalUsersSeeder::class);

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
            $roomActorId = DB::table('users')
                ->whereIn('email', ['admindev@tclass.local', 'registrar.faculty@tclass.local'])
                ->orderBy('id')
                ->value('id');

            $roomCatalog = [
                ['room_code' => 'L306A', 'title' => 'Room 306A - Lecture Hall', 'description' => 'Projector-ready lecture room with smart board and dual AC.', 'icon_key' => 'presentation', 'building' => 'L', 'capacity' => 45, 'is_active' => 1],
                ['room_code' => 'L206', 'title' => 'Room 206 - Computer Lab', 'description' => 'Laboratory room with 40 desktop seats and wired LAN.', 'icon_key' => 'monitor', 'building' => 'L', 'capacity' => 40, 'is_active' => 1],
                ['room_code' => 'L204', 'title' => 'Room 204 - Programming Lab', 'description' => 'Programming laboratory with Linux dual boot workstations.', 'icon_key' => 'cpu', 'building' => 'L', 'capacity' => 36, 'is_active' => 1],
                ['room_code' => 'L309', 'title' => 'Room 309 - Smart Classroom', 'description' => 'Lecture room equipped for hybrid delivery and recording.', 'icon_key' => 'presentation', 'building' => 'L', 'capacity' => 50, 'is_active' => 1],
                ['room_code' => 'SC101', 'title' => 'Science Lab 101', 'description' => 'General science room for demonstrations and practical sessions.', 'icon_key' => 'flask-conical', 'building' => 'Science', 'capacity' => 32, 'is_active' => 1],
                ['room_code' => 'SC102', 'title' => 'Science Lab 102', 'description' => 'Wet lab with prep counters and secured storage cabinets.', 'icon_key' => 'microscope', 'building' => 'Science', 'capacity' => 30, 'is_active' => 1],
                ['room_code' => 'LIB201', 'title' => 'Library Discussion 201', 'description' => 'Quiet collaborative room inside the library annex.', 'icon_key' => 'library', 'building' => 'Library', 'capacity' => 28, 'is_active' => 1],
                ['room_code' => 'AVR1', 'title' => 'Audio Visual Room 1', 'description' => 'Multi-media presentation room for seminars and defense.', 'icon_key' => 'users', 'building' => 'Admin', 'capacity' => 80, 'is_active' => 1],
                ['room_code' => 'A103', 'title' => 'Room A103 - General Classroom', 'description' => 'Ground floor classroom with flexible desk layout.', 'icon_key' => 'school', 'building' => 'A', 'capacity' => 45, 'is_active' => 1],
                ['room_code' => 'A205', 'title' => 'Room A205 - General Classroom', 'description' => 'Second floor classroom near registrar wing.', 'icon_key' => 'door-open', 'building' => 'A', 'capacity' => 42, 'is_active' => 1],
                ['room_code' => 'B112', 'title' => 'Room B112 - General Classroom', 'description' => 'Ventilated classroom for regular lecture blocks.', 'icon_key' => 'school', 'building' => 'B', 'capacity' => 38, 'is_active' => 1],
                ['room_code' => 'B301', 'title' => 'Room B301 - Capstone Studio', 'description' => 'Project collaboration room with movable whiteboards.', 'icon_key' => 'book-open', 'building' => 'B', 'capacity' => 35, 'is_active' => 1],
                ['room_code' => 'GYM-MP1', 'title' => 'Gym Multi Purpose Hall', 'description' => 'Large indoor venue for PE classes and events.', 'icon_key' => 'building-2', 'building' => 'Gym', 'capacity' => 120, 'is_active' => 1],
                ['room_code' => 'OLD-LAB1', 'title' => 'Old Lab 1 (Maintenance)', 'description' => 'Legacy laboratory kept for archive schedules only.', 'icon_key' => 'monitor', 'building' => 'Old Annex', 'capacity' => 24, 'is_active' => 0],
            ];

            foreach ($roomCatalog as $room) {
                $roomSeed = [
                    'building' => $room['building'] ?? null,
                    'capacity' => $room['capacity'] ?? null,
                    'is_active' => (int) ($room['is_active'] ?? 1),
                    'updated_at' => now(),
                    'created_at' => now(),
                ];

                if (Schema::hasColumn('schedule_rooms', 'title')) {
                    $roomSeed['title'] = $room['title'] ?? null;
                }
                if (Schema::hasColumn('schedule_rooms', 'description')) {
                    $roomSeed['description'] = $room['description'] ?? null;
                }
                if (Schema::hasColumn('schedule_rooms', 'icon_key')) {
                    $roomSeed['icon_key'] = $room['icon_key'] ?? null;
                }
                if (Schema::hasColumn('schedule_rooms', 'created_by_user_id')) {
                    $roomSeed['created_by_user_id'] = $roomActorId;
                }
                if (Schema::hasColumn('schedule_rooms', 'updated_by_user_id')) {
                    $roomSeed['updated_by_user_id'] = $roomActorId;
                }

                DB::table('schedule_rooms')->updateOrInsert(
                    ['room_code' => $room['room_code']],
                    $roomSeed
                );
            }
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
                    $scheduleSeed = [
                        'teacher_id' => $row['teacher_id'],
                        'room_id' => $row['room_id'],
                        'schedule_text' => "{$row['day']} {$startLabel} - {$endLabel}",
                        'capacity' => 40,
                        'is_active' => 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];
                    $scheduleActorId = DB::table('users')
                        ->whereIn('email', ['admindev@tclass.local', 'registrar.faculty@tclass.local'])
                        ->orderBy('id')
                        ->value('id');
                    if (Schema::hasColumn('class_offerings', 'created_by_user_id')) {
                        $scheduleSeed['created_by_user_id'] = $scheduleActorId;
                    }
                    if (Schema::hasColumn('class_offerings', 'updated_by_user_id')) {
                        $scheduleSeed['updated_by_user_id'] = $scheduleActorId;
                    }

                    DB::table('class_offerings')->updateOrInsert(
                        [
                            'period_id' => $periodId,
                            'course_id' => $courseId,
                            'section_id' => $sectionId,
                            'day_of_week' => $row['day'],
                            'start_time' => $row['start'] . ':00',
                            'end_time' => $row['end'] . ':00',
                        ],
                        $scheduleSeed
                    );
                }
            }
        }

        $this->call(FacultyPortalSeeder::class);
        $this->call(ClassScheduleQaSeeder::class);
    }
}
