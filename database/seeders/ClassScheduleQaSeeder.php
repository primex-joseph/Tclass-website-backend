<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClassScheduleQaSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('class_offerings') ||
            ! Schema::hasTable('enrollment_periods') ||
            ! Schema::hasTable('courses') ||
            ! Schema::hasTable('schedule_sections') ||
            ! Schema::hasTable('schedule_teachers') ||
            ! Schema::hasTable('schedule_rooms')
        ) {
            return;
        }

        $periodId = DB::table('enrollment_periods')
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->value('id');

        if (! $periodId) {
            return;
        }

        $rooms = DB::table('schedule_rooms')
            ->where('is_active', 1)
            ->orderBy('room_code')
            ->get(['id', 'room_code', 'capacity']);

        $teachers = DB::table('schedule_teachers')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get(['id']);

        $sections = DB::table('schedule_sections')
            ->where('is_active', 1)
            ->orderBy('section_code')
            ->get(['id']);

        $courses = DB::table('courses')
            ->where('is_active', 1)
            ->where('semester', 1)
            ->orderBy('year_level')
            ->orderBy('code')
            ->get(['id']);

        if ($rooms->isEmpty() || $teachers->isEmpty() || $sections->isEmpty() || $courses->isEmpty()) {
            return;
        }

        $slots = [
            ['day' => 'Mon', 'start' => '07:30:00', 'end' => '09:00:00'],
            ['day' => 'Mon', 'start' => '09:00:00', 'end' => '10:30:00'],
            ['day' => 'Mon', 'start' => '10:30:00', 'end' => '12:00:00'],
            ['day' => 'Mon', 'start' => '13:00:00', 'end' => '14:30:00'],
            ['day' => 'Tue', 'start' => '07:30:00', 'end' => '09:00:00'],
            ['day' => 'Tue', 'start' => '09:00:00', 'end' => '10:30:00'],
            ['day' => 'Tue', 'start' => '10:30:00', 'end' => '12:00:00'],
            ['day' => 'Tue', 'start' => '13:00:00', 'end' => '14:30:00'],
            ['day' => 'Wed', 'start' => '07:30:00', 'end' => '09:00:00'],
            ['day' => 'Wed', 'start' => '09:00:00', 'end' => '10:30:00'],
            ['day' => 'Wed', 'start' => '10:30:00', 'end' => '12:00:00'],
            ['day' => 'Wed', 'start' => '13:00:00', 'end' => '14:30:00'],
            ['day' => 'Thu', 'start' => '07:30:00', 'end' => '09:00:00'],
            ['day' => 'Thu', 'start' => '09:00:00', 'end' => '10:30:00'],
            ['day' => 'Thu', 'start' => '10:30:00', 'end' => '12:00:00'],
            ['day' => 'Thu', 'start' => '13:00:00', 'end' => '14:30:00'],
            ['day' => 'Fri', 'start' => '08:00:00', 'end' => '10:00:00'],
            ['day' => 'Fri', 'start' => '10:00:00', 'end' => '12:00:00'],
            ['day' => 'Fri', 'start' => '13:00:00', 'end' => '15:00:00'],
            ['day' => 'Sat', 'start' => '08:00:00', 'end' => '11:00:00'],
        ];

        $actorId = DB::table('users')
            ->whereIn('email', ['registrar.faculty@tclass.local', 'admindev@tclass.local'])
            ->orderBy('id')
            ->value('id');

        $seedCount = min($rooms->count(), $courses->count(), count($slots));
        for ($i = 0; $i < $seedCount; $i++) {
            $room = $rooms[$i];
            $course = $courses[$i];
            $teacher = $teachers[$i % $teachers->count()];
            $section = $sections[$i % $sections->count()];
            $slot = $slots[$i];

            $seed = [
                'teacher_id' => (int) $teacher->id,
                'room_id' => (int) $room->id,
                'schedule_text' => $this->formatScheduleText($slot['day'], $slot['start'], $slot['end']),
                'capacity' => (int) ($room->capacity ?: 40),
                'is_active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ];

            if (Schema::hasColumn('class_offerings', 'created_by_user_id')) {
                $seed['created_by_user_id'] = $actorId;
            }
            if (Schema::hasColumn('class_offerings', 'updated_by_user_id')) {
                $seed['updated_by_user_id'] = $actorId;
            }

            DB::table('class_offerings')->updateOrInsert(
                [
                    'period_id' => (int) $periodId,
                    'course_id' => (int) $course->id,
                    'section_id' => (int) $section->id,
                    'day_of_week' => $slot['day'],
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                ],
                $seed
            );
        }
    }

    private function formatScheduleText(string $day, string $start, string $end): string
    {
        $startLabel = date('h:i A', strtotime("1970-01-01 {$start}"));
        $endLabel = date('h:i A', strtotime("1970-01-01 {$end}"));

        return "{$day} {$startLabel} - {$endLabel}";
    }
}

