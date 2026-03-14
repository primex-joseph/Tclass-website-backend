<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\FacultyWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FacultyPortalSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('faculty_positions') || ! Schema::hasTable('faculty_profiles')) {
            return;
        }

        FacultyWorkflow::syncPermissionCatalog();
        $this->seedPositions();
        $this->seedFacultyDevProfile();
        $this->seedFacultyStudentsAndOfferings();
        $this->assignTemplatesToFacultyUsers();
    }

    private function seedPositions(): void
    {
        $rows = [
            ['code' => 'instructor_i', 'title' => 'Instructor I', 'category' => 'Teaching', 'sort_order' => 10],
            ['code' => 'instructor_ii', 'title' => 'Instructor II', 'category' => 'Teaching', 'sort_order' => 20],
            ['code' => 'instructor_iii', 'title' => 'Instructor III', 'category' => 'Teaching', 'sort_order' => 30],
            ['code' => 'assistant_professor_i', 'title' => 'Assistant Professor I', 'category' => 'Teaching', 'sort_order' => 40],
            ['code' => 'assistant_professor_ii', 'title' => 'Assistant Professor II', 'category' => 'Teaching', 'sort_order' => 50],
            ['code' => 'assistant_professor_iii', 'title' => 'Assistant Professor III', 'category' => 'Teaching', 'sort_order' => 60],
            ['code' => 'assistant_professor_iv', 'title' => 'Assistant Professor IV', 'category' => 'Teaching', 'sort_order' => 70],
            ['code' => 'associate_professor_i', 'title' => 'Associate Professor I', 'category' => 'Teaching', 'sort_order' => 80],
            ['code' => 'associate_professor_ii', 'title' => 'Associate Professor II', 'category' => 'Teaching', 'sort_order' => 90],
            ['code' => 'associate_professor_iii', 'title' => 'Associate Professor III', 'category' => 'Teaching', 'sort_order' => 100],
            ['code' => 'associate_professor_iv', 'title' => 'Associate Professor IV', 'category' => 'Teaching', 'sort_order' => 110],
            ['code' => 'associate_professor_v', 'title' => 'Associate Professor V', 'category' => 'Teaching', 'sort_order' => 120],
            ['code' => 'professor_i', 'title' => 'Professor I', 'category' => 'Teaching', 'sort_order' => 130],
            ['code' => 'professor_ii', 'title' => 'Professor II', 'category' => 'Teaching', 'sort_order' => 140],
            ['code' => 'professor_iii', 'title' => 'Professor III', 'category' => 'Teaching', 'sort_order' => 150],
            ['code' => 'professor_iv', 'title' => 'Professor IV', 'category' => 'Teaching', 'sort_order' => 160],
            ['code' => 'professor_v', 'title' => 'Professor V', 'category' => 'Teaching', 'sort_order' => 170],
            ['code' => 'professor_vi', 'title' => 'Professor VI', 'category' => 'Teaching', 'sort_order' => 180],
            ['code' => 'lecturer', 'title' => 'Lecturer', 'category' => 'Teaching', 'sort_order' => 190],
            ['code' => 'clinical_instructor', 'title' => 'Clinical Instructor', 'category' => 'Teaching', 'sort_order' => 200],
            ['code' => 'program_head', 'title' => 'Program Head', 'category' => 'Academic Administration', 'sort_order' => 210],
            ['code' => 'department_chairperson', 'title' => 'Department Chairperson', 'category' => 'Academic Administration', 'sort_order' => 220],
            ['code' => 'college_dean', 'title' => 'College Dean', 'category' => 'Academic Administration', 'sort_order' => 230],
            ['code' => 'assistant_registrar', 'title' => 'Assistant Registrar', 'category' => 'Academic Administration', 'sort_order' => 240],
            ['code' => 'registrar', 'title' => 'Registrar', 'category' => 'Academic Administration', 'sort_order' => 250],
            ['code' => 'director_academic_affairs', 'title' => 'Director for Academic Affairs', 'category' => 'Academic Administration', 'sort_order' => 260],
        ];

        foreach ($rows as $row) {
            DB::table('faculty_positions')->updateOrInsert(
                ['code' => $row['code']],
                $row + ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function seedFacultyDevProfile(): void
    {
        $faculty = User::query()->where('email', 'facultydev@tclass.local')->first();
        if (! $faculty) {
            return;
        }

        $positionId = DB::table('faculty_positions')->where('code', 'instructor_i')->value('id');

        $teacherId = null;
        if (Schema::hasTable('schedule_teachers')) {
            $teacherId = DB::table('schedule_teachers')->updateOrInsert(
                ['email' => $faculty->email],
                [
                    'user_id' => $faculty->id,
                    'employee_code' => '19-00123',
                    'full_name' => 'Prof. Faculty Dev',
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $teacherId = DB::table('schedule_teachers')->where('email', $faculty->email)->value('id');
        }

        DB::table('faculty_profiles')->updateOrInsert(
            ['user_id' => $faculty->id],
            [
                'employee_id' => '19-00123',
                'department' => 'College of Information Technology',
                'position_id' => $positionId ?: null,
                'schedule_teacher_id' => $teacherId ?: null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function seedFacultyStudentsAndOfferings(): void
    {
        if (
            ! Schema::hasTable('class_offerings') ||
            ! Schema::hasTable('enrollments') ||
            ! Schema::hasTable('enrollment_periods') ||
            ! Schema::hasTable('schedule_sections') ||
            ! Schema::hasTable('schedule_rooms')
        ) {
            return;
        }

        $faculty = User::query()->where('email', 'facultydev@tclass.local')->first();
        if (! $faculty) {
            return;
        }

        $teacherId = DB::table('faculty_profiles')->where('user_id', $faculty->id)->value('schedule_teacher_id');
        $periodId = DB::table('enrollment_periods')->where('is_active', 1)->orderByDesc('id')->value('id');
        $sectionId = DB::table('schedule_sections')->where('section_code', 'BSIT-4A')->value('id');
        $roomId = DB::table('schedule_rooms')->where('room_code', 'L306A')->value('id')
            ?: DB::table('schedule_rooms')->orderBy('id')->value('id');

        if (! $teacherId || ! $periodId || ! $sectionId || ! $roomId) {
            return;
        }

        $courseCodes = [
            ['code' => 'THEO1', 'day' => 'Fri', 'start' => '10:30:00', 'end' => '11:30:00'],
            ['code' => 'ORIENT1', 'day' => 'Fri', 'start' => '13:00:00', 'end' => '14:00:00'],
            ['code' => 'IT64', 'day' => 'Sat', 'start' => '08:30:00', 'end' => '10:00:00'],
        ];

        $offeringIds = [];
        foreach ($courseCodes as $index => $row) {
            $courseId = DB::table('courses')->where('code', $row['code'])->value('id');
            if (! $courseId) {
                continue;
            }

            DB::table('class_offerings')->updateOrInsert(
                [
                    'period_id' => $periodId,
                    'course_id' => $courseId,
                    'section_id' => $sectionId,
                    'teacher_id' => $teacherId,
                    'day_of_week' => $row['day'],
                    'start_time' => $row['start'],
                    'end_time' => $row['end'],
                ],
                [
                    'room_id' => $roomId,
                    'schedule_text' => $this->formatScheduleText($row['day'], $row['start'], $row['end']),
                    'capacity' => 40,
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $offeringId = DB::table('class_offerings')
                ->where('period_id', $periodId)
                ->where('course_id', $courseId)
                ->where('teacher_id', $teacherId)
                ->value('id');

            if ($offeringId) {
                $offeringIds[$index] = [
                    'id' => (int) $offeringId,
                    'course_id' => (int) $courseId,
                ];
            }
        }

        if ($offeringIds === []) {
            return;
        }

        $studentIds = User::query()
            ->whereIn('email', [
                'studentdev@tclass.local',
                'facultystudent1@tclass.local',
                'facultystudent2@tclass.local',
            ])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($studentIds === []) {
            return;
        }

        foreach ($studentIds as $studentIndex => $studentId) {
            foreach ($offeringIds as $offeringIndex => $offeringRow) {
                if ($studentIndex === 2 && $offeringIndex === 2) {
                    continue;
                }

                DB::table('enrollments')->updateOrInsert(
                    [
                        'user_id' => $studentId,
                        'course_id' => $offeringRow['course_id'],
                        'period_id' => $periodId,
                    ],
                    [
                        'offering_id' => $offeringRow['id'],
                        'status' => 'official',
                        'requested_at' => now(),
                        'assessed_at' => now(),
                        'decided_at' => now(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        foreach ($offeringIds as $offeringIndex => $offeringRow) {
            DB::table('faculty_assignments')->updateOrInsert(
                [
                    'offering_id' => $offeringRow['id'],
                    'title' => 'Seeded Assignment ' . ($offeringIndex + 1),
                ],
                [
                    'created_by_user_id' => $faculty->id,
                    'description' => 'Auto-seeded assignment for faculty portal validation.',
                    'points' => 100,
                    'due_at' => now()->addDays(7 + $offeringIndex),
                    'is_published' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $enrollmentRows = DB::table('enrollments')
            ->where('period_id', $periodId)
            ->whereIn('offering_id', collect($offeringIds)->pluck('id')->all())
            ->get(['id', 'user_id', 'course_id', 'offering_id']);

        foreach ($enrollmentRows as $index => $row) {
            $baseGrade = match ($index % 3) {
                0 => 1.50,
                1 => 1.75,
                default => 2.00,
            };

            DB::table('faculty_grade_entries')->updateOrInsert(
                [
                    'offering_id' => (int) $row->offering_id,
                    'enrollment_id' => (int) $row->id,
                ],
                [
                    'midterm_grade' => $baseGrade,
                    'final_grade' => $baseGrade,
                    're_exam_grade' => null,
                    'status' => 'posted',
                    'posted_by_user_id' => $faculty->id,
                    'posted_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('student_course_results')->updateOrInsert(
                [
                    'user_id' => (int) $row->user_id,
                    'course_id' => (int) $row->course_id,
                ],
                [
                    'grade' => $baseGrade,
                    'status' => 'passed',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function assignTemplatesToFacultyUsers(): void
    {
        $rows = DB::table('users')
            ->join('portal_user_roles', function ($join) {
                $join->on('portal_user_roles.user_id', '=', 'users.id')
                    ->where('portal_user_roles.role', '=', 'faculty')
                    ->where('portal_user_roles.is_active', '=', 1);
            })
            ->leftJoin('faculty_profiles', 'faculty_profiles.user_id', '=', 'users.id')
            ->leftJoin('faculty_positions', 'faculty_positions.id', '=', 'faculty_profiles.position_id')
            ->select('users.id', 'faculty_positions.title as position_title')
            ->get();

        foreach ($rows as $row) {
            $user = User::query()->find((int) $row->id);
            if (! $user) {
                continue;
            }

            $template = FacultyWorkflow::templateFromPosition((string) ($row->position_title ?? ''));
            FacultyWorkflow::assignTemplate($user, $template);
        }
    }

    private function formatScheduleText(string $day, string $start, string $end): string
    {
        $startLabel = date('h:i A', strtotime("1970-01-01 {$start}"));
        $endLabel = date('h:i A', strtotime("1970-01-01 {$end}"));

        return "{$day} {$startLabel} - {$endLabel}";
    }
}
