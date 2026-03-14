<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FacultyWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class FacultyPortalController extends Controller
{
    private function ensureFaculty(Request $request): ?JsonResponse
    {
        $role = DB::table('portal_user_roles')
            ->where('user_id', $request->user()->id)
            ->where('role', 'faculty')
            ->where('is_active', 1)
            ->exists();

        if (! $role) {
            return response()->json(['message' => 'Forbidden. Faculty role required.'], 403);
        }

        return null;
    }

    private function activePeriodId(): ?int
    {
        return DB::table('enrollment_periods')->where('is_active', 1)->orderByDesc('id')->value('id');
    }

    /**
     * @return array{
     *   profile: object|null,
     *   teacher_id: int|null,
     *   permissions: array<int, string>,
     *   template: string|null
     * }
     */
    private function facultyContext(User $user): array
    {
        $profile = DB::table('faculty_profiles as fp')
            ->leftJoin('faculty_positions as pos', 'pos.id', '=', 'fp.position_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'fp.schedule_teacher_id')
            ->where('fp.user_id', $user->id)
            ->select(
                'fp.user_id',
                'fp.employee_id',
                'fp.department',
                'fp.position_id',
                'fp.schedule_teacher_id',
                'pos.title as position_title',
                'st.full_name as teacher_name'
            )
            ->first();

        $teacherId = $profile?->schedule_teacher_id
            ? (int) $profile->schedule_teacher_id
            : (DB::table('schedule_teachers')->where('user_id', $user->id)->value('id') ?: null);

        return [
            'profile' => $profile,
            'teacher_id' => $teacherId ? (int) $teacherId : null,
            'permissions' => FacultyWorkflow::effectivePermissions($user),
            'template' => FacultyWorkflow::currentTemplate($user),
        ];
    }

    private function can(User $user, string $permission): bool
    {
        return FacultyWorkflow::hasPermission($user, $permission);
    }

    private function visibleOfferingsQuery(User $user, ?int $periodId, bool $includeAllForRegistrar = false)
    {
        $context = $this->facultyContext($user);
        $teacherId = $context['teacher_id'];
        $isRegistrar = $includeAllForRegistrar && $this->can($user, 'faculty.schedules.manage');

        $query = DB::table('class_offerings as o')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('enrollment_periods as p', 'p.id', '=', 'o.period_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->when($periodId, fn ($builder) => $builder->where('o.period_id', $periodId))
            ->select(
                'o.id',
                'o.period_id',
                'o.course_id',
                'o.section_id',
                'o.teacher_id',
                'o.room_id',
                'o.day_of_week',
                'o.start_time',
                'o.end_time',
                'o.schedule_text',
                'o.capacity',
                'o.is_active',
                'p.name as period_name',
                'c.code as course_code',
                'c.title as course_title',
                'c.units',
                'ss.section_code',
                'st.full_name as teacher_name',
                'sr.room_code'
            )
            ->orderBy('o.day_of_week')
            ->orderBy('o.start_time')
            ->orderBy('c.code');

        if (! $isRegistrar) {
            if (! $teacherId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('o.teacher_id', $teacherId);
            }
        }

        return $query;
    }

    private function visibleOfferingIds(User $user, ?int $periodId, bool $includeAllForRegistrar = false): array
    {
        return $this->visibleOfferingsQuery($user, $periodId, $includeAllForRegistrar)
            ->pluck('o.id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function ensureVisibleOffering(User $user, int $offeringId, bool $allowRegistrarAll = false): ?JsonResponse
    {
        if (! in_array($offeringId, $this->visibleOfferingIds($user, null, $allowRegistrarAll), true)) {
            return response()->json(['message' => 'You do not have access to this class offering.'], 403);
        }

        return null;
    }

    private function enrolledCountMap(array $offeringIds): array
    {
        if ($offeringIds === []) {
            return [];
        }

        return DB::table('enrollments')
            ->whereIn('offering_id', $offeringIds)
            ->whereIn('status', ['draft', 'unofficial', 'official'])
            ->select('offering_id', DB::raw('COUNT(*) as total'))
            ->groupBy('offering_id')
            ->pluck('total', 'offering_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    public function me(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        $context = $this->facultyContext($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'faculty',
            ],
            'profile' => [
                'employee_id' => $context['profile']?->employee_id,
                'department' => $context['profile']?->department,
                'position_id' => $context['profile']?->position_id,
                'position' => $context['profile']?->position_title,
                'schedule_teacher_id' => $context['profile']?->schedule_teacher_id,
                'teacher_name' => $context['profile']?->teacher_name,
            ],
            'permissions' => $context['permissions'],
            'template' => $context['template'],
        ]);
    }

    public function periods(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        return response()->json([
            'periods' => DB::table('enrollment_periods')->orderByDesc('id')->get(),
            'active_period_id' => $this->activePeriodId(),
        ]);
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        $periodId = (int) ($request->query('period_id') ?: $this->activePeriodId());
        $context = $this->facultyContext($user);
        $offerings = $this->visibleOfferingsQuery($user, $periodId, false)->get();
        $offeringIds = $offerings->pluck('id')->map(fn ($value) => (int) $value)->all();

        $studentsCount = DB::table('enrollments')
            ->whereIn('offering_id', $offeringIds ?: [0])
            ->whereIn('status', ['unofficial', 'official'])
            ->distinct('user_id')
            ->count('user_id');

        $todayCode = now()->format('D');
        $todaySchedule = $offerings
            ->where('day_of_week', $todayCode)
            ->values()
            ->map(fn ($row) => [
                'offering_id' => (int) $row->id,
                'course_code' => (string) $row->course_code,
                'course_title' => (string) $row->course_title,
                'section_code' => (string) ($row->section_code ?? ''),
                'schedule_text' => (string) ($row->schedule_text ?? ''),
                'room_code' => (string) ($row->room_code ?? ''),
            ])
            ->all();

        return response()->json([
            'profile' => [
                'employee_id' => $context['profile']?->employee_id,
                'department' => $context['profile']?->department,
                'position' => $context['profile']?->position_title,
                'position_id' => $context['profile']?->position_id,
            ],
            'stats' => [
                'classes' => count($offeringIds),
                'students' => $studentsCount,
                'load_count' => count($offeringIds),
            ],
            'today_schedule' => $todaySchedule,
            'permissions' => $context['permissions'],
            'template' => $context['template'],
            'active_period_id' => $periodId,
        ]);
    }

    public function classSchedules(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.schedules.view')) {
            return response()->json(['message' => 'You do not have access to schedules.'], 403);
        }

        $periodId = (int) ($request->query('period_id') ?: $this->activePeriodId());
        $offerings = $this->visibleOfferingsQuery($user, $periodId, true)->get();

        return response()->json([
            'items' => $offerings->map(fn ($row) => [
                'offering_id' => (int) $row->id,
                'course_code' => (string) $row->course_code,
                'course_title' => (string) $row->course_title,
                'section_code' => (string) ($row->section_code ?? ''),
                'teacher_name' => (string) ($row->teacher_name ?? ''),
                'room_code' => (string) ($row->room_code ?? ''),
                'day_of_week' => (string) $row->day_of_week,
                'start_time' => (string) $row->start_time,
                'end_time' => (string) $row->end_time,
                'schedule_text' => (string) ($row->schedule_text ?? ''),
                'period_name' => (string) ($row->period_name ?? ''),
            ])->values(),
            'can_manage' => $this->can($user, 'faculty.schedules.manage'),
            'can_export' => $this->can($user, 'faculty.schedules.export'),
        ]);
    }

    public function updateSchedule(Request $request, int $offeringId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.schedules.manage')) {
            return response()->json(['message' => 'Only authorized faculty can update schedules.'], 403);
        }

        $allowRegistrarAll = FacultyWorkflow::currentTemplate($user) === FacultyWorkflow::TEMPLATE_REGISTRAR;
        if ($resp = $this->ensureVisibleOffering($user, $offeringId, $allowRegistrarAll)) {
            return $resp;
        }

        $validated = $request->validate([
            'day_of_week' => ['required', 'in:Mon,Tue,Wed,Thu,Fri,Sat'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room_id' => ['nullable', 'integer', 'exists:schedule_rooms,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:schedule_teachers,id'],
            'section_id' => ['nullable', 'integer', 'exists:schedule_sections,id'],
        ]);

        $payload = array_filter([
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'] . ':00',
            'end_time' => $validated['end_time'] . ':00',
            'room_id' => $validated['room_id'] ?? null,
            'teacher_id' => $validated['teacher_id'] ?? null,
            'section_id' => $validated['section_id'] ?? null,
            'schedule_text' => $this->formatScheduleText($validated['day_of_week'], $validated['start_time'], $validated['end_time']),
            'updated_at' => now(),
        ], fn ($value) => $value !== null);

        DB::table('class_offerings')->where('id', $offeringId)->update($payload);

        return response()->json(['message' => 'Schedule updated successfully.']);
    }

    public function exportSchedules(Request $request)
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.schedules.export')) {
            return response()->json(['message' => 'Only authorized faculty can export schedules.'], 403);
        }

        $periodId = (int) ($request->query('period_id') ?: $this->activePeriodId());
        $offerings = $this->visibleOfferingsQuery($user, $periodId, true)->get();
        $rows = collect([
            ['Course Code', 'Course Title', 'Section', 'Teacher', 'Room', 'Schedule'],
        ])->merge(
            $offerings->map(fn ($row) => [
                $row->course_code,
                $row->course_title,
                $row->section_code,
                $row->teacher_name,
                $row->room_code,
                $row->schedule_text,
            ])
        );

        return Response::streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'faculty-schedules.csv', ['Content-Type' => 'text/csv']);
    }

    public function classLists(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.class_lists.view')) {
            return response()->json(['message' => 'You do not have access to class lists.'], 403);
        }

        $periodId = (int) ($request->query('period_id') ?: $this->activePeriodId());
        $offerings = $this->visibleOfferingsQuery($user, $periodId, false)->get();
        $offeringIds = $offerings->pluck('id')->map(fn ($value) => (int) $value)->all();
        $enrolledCountMap = $this->enrolledCountMap($offeringIds);

        return response()->json([
            'items' => $offerings->map(fn ($row) => [
                'offering_id' => (int) $row->id,
                'course_code' => (string) $row->course_code,
                'course_title' => (string) $row->course_title,
                'section_code' => (string) ($row->section_code ?? ''),
                'schedule_text' => (string) ($row->schedule_text ?? ''),
                'room_code' => (string) ($row->room_code ?? ''),
                'teacher_name' => (string) ($row->teacher_name ?? ''),
                'enrolled_count' => $enrolledCountMap[(int) $row->id] ?? 0,
            ])->values(),
            'can_export' => $this->can($user, 'faculty.class_lists.export'),
        ]);
    }

    public function exportClassList(Request $request, int $offeringId)
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.class_lists.export')) {
            return response()->json(['message' => 'Only authorized faculty can export class lists.'], 403);
        }

        if ($resp = $this->ensureVisibleOffering($user, $offeringId, true)) {
            return $resp;
        }

        $rows = DB::table('enrollments as e')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.offering_id', $offeringId)
            ->whereIn('e.status', ['unofficial', 'official'])
            ->orderBy('u.name')
            ->get(['u.student_number', 'u.name', 'u.email', 'e.status']);

        return Response::streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Student Number', 'Name', 'Email', 'Status']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->student_number, $row->name, $row->email, $row->status]);
            }
            fclose($handle);
        }, "class-list-{$offeringId}.csv", ['Content-Type' => 'text/csv']);
    }

    public function classes(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        $periodId = (int) ($request->query('period_id') ?: $this->activePeriodId());
        $offerings = $this->visibleOfferingsQuery($user, $periodId, false)->get();
        $offeringIds = $offerings->pluck('id')->map(fn ($value) => (int) $value)->all();
        $enrolledCountMap = $this->enrolledCountMap($offeringIds);
        $syllabi = DB::table('faculty_syllabi')->whereIn('offering_id', $offeringIds ?: [0])->get()->keyBy('offering_id');

        return response()->json([
            'items' => $offerings->map(fn ($row) => [
                'offering_id' => (int) $row->id,
                'course_code' => (string) $row->course_code,
                'course_title' => (string) $row->course_title,
                'section_code' => (string) ($row->section_code ?? ''),
                'schedule_text' => (string) ($row->schedule_text ?? ''),
                'room_code' => (string) ($row->room_code ?? ''),
                'teacher_name' => (string) ($row->teacher_name ?? ''),
                'enrolled_count' => $enrolledCountMap[(int) $row->id] ?? 0,
                'syllabus' => isset($syllabi[$row->id]) ? [
                    'file_name' => (string) $syllabi[$row->id]->file_name,
                    'uploaded_at' => (string) $syllabi[$row->id]->updated_at,
                ] : null,
            ])->values(),
            'can_upload_syllabus' => $this->can($user, 'faculty.syllabi.upload'),
            'can_manage_assignments' => $this->can($user, 'faculty.assignments.manage'),
        ]);
    }

    public function uploadSyllabus(Request $request, int $offeringId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.syllabi.upload')) {
            return response()->json(['message' => 'Only authorized instructors can upload syllabi.'], 403);
        }

        if ($resp = $this->ensureVisibleOffering($user, $offeringId, false)) {
            return $resp;
        }

        $validated = $request->validate([
            'syllabus' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $file = $validated['syllabus'];
        $path = $file->store('faculty/syllabi', 'public');

        DB::table('faculty_syllabi')->updateOrInsert(
            ['offering_id' => $offeringId],
            [
                'uploaded_by_user_id' => $user->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['message' => 'Syllabus uploaded successfully.']);
    }

    public function students(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.students.view')) {
            return response()->json(['message' => 'You do not have access to student records.'], 403);
        }

        $periodId = (int) ($request->query('period_id') ?: $this->activePeriodId());
        $offeringIds = $this->visibleOfferingIds($user, $periodId, false);
        if ($offeringIds === []) {
            return response()->json(['items' => []]);
        }

        $rows = DB::table('enrollments as e')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->join('class_offerings as o', 'o.id', '=', 'e.offering_id')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('faculty_grade_entries as fge', function ($join) {
                $join->on('fge.enrollment_id', '=', 'e.id')
                    ->on('fge.offering_id', '=', 'e.offering_id');
            })
            ->whereIn('e.offering_id', $offeringIds)
            ->whereIn('e.status', ['unofficial', 'official'])
            ->orderBy('u.name')
            ->select(
                'u.id as user_id',
                'u.name',
                'u.email',
                'u.student_number',
                'c.code as course_code',
                'c.title as course_title',
                'ss.section_code',
                'fge.midterm_grade',
                'fge.final_grade',
                'fge.re_exam_grade',
                'e.status as enrollment_status'
            )
            ->get();

        return response()->json([
            'items' => $rows->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'student_number' => (string) ($row->student_number ?? ''),
                'course_code' => (string) $row->course_code,
                'course_title' => (string) $row->course_title,
                'section_code' => (string) ($row->section_code ?? ''),
                'midterm_grade' => $row->midterm_grade !== null ? (float) $row->midterm_grade : null,
                'final_grade' => $row->final_grade !== null ? (float) $row->final_grade : null,
                're_exam_grade' => $row->re_exam_grade !== null ? (float) $row->re_exam_grade : null,
                'enrollment_status' => (string) $row->enrollment_status,
            ])->values(),
        ]);
    }

    public function assignments(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.assignments.view')) {
            return response()->json(['message' => 'You do not have access to assignments.'], 403);
        }

        $periodId = (int) ($request->query('period_id') ?: $this->activePeriodId());
        $offeringIds = $this->visibleOfferingIds($user, $periodId, false);
        $rows = DB::table('faculty_assignments as fa')
            ->join('class_offerings as o', 'o.id', '=', 'fa.offering_id')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->whereIn('fa.offering_id', $offeringIds ?: [0])
            ->orderByDesc('fa.id')
            ->select('fa.*', 'c.code as course_code', 'c.title as course_title', 'ss.section_code')
            ->get();

        return response()->json([
            'items' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'offering_id' => (int) $row->offering_id,
                'course_code' => (string) $row->course_code,
                'course_title' => (string) $row->course_title,
                'section_code' => (string) ($row->section_code ?? ''),
                'title' => (string) $row->title,
                'description' => (string) ($row->description ?? ''),
                'points' => (int) $row->points,
                'due_at' => $row->due_at,
                'is_published' => (bool) $row->is_published,
            ])->values(),
            'can_manage' => $this->can($user, 'faculty.assignments.manage'),
        ]);
    }

    public function storeAssignment(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.assignments.manage')) {
            return response()->json(['message' => 'Only authorized faculty can manage assignments.'], 403);
        }

        $validated = $request->validate([
            'offering_id' => ['required', 'integer', 'exists:class_offerings,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        if ($resp = $this->ensureVisibleOffering($user, (int) $validated['offering_id'], false)) {
            return $resp;
        }

        $id = DB::table('faculty_assignments')->insertGetId([
            'offering_id' => (int) $validated['offering_id'],
            'created_by_user_id' => $user->id,
            'title' => trim((string) $validated['title']),
            'description' => $validated['description'] ?? null,
            'points' => (int) ($validated['points'] ?? 100),
            'due_at' => $validated['due_at'] ?? null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Assignment created successfully.', 'id' => $id], 201);
    }

    public function updateAssignment(Request $request, int $assignmentId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.assignments.manage')) {
            return response()->json(['message' => 'Only authorized faculty can manage assignments.'], 403);
        }

        $assignment = DB::table('faculty_assignments')->where('id', $assignmentId)->first();
        if (! $assignment) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        if ($resp = $this->ensureVisibleOffering($user, (int) $assignment->offering_id, false)) {
            return $resp;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        DB::table('faculty_assignments')->where('id', $assignmentId)->update([
            'title' => trim((string) $validated['title']),
            'description' => $validated['description'] ?? null,
            'points' => (int) ($validated['points'] ?? 100),
            'due_at' => $validated['due_at'] ?? null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Assignment updated successfully.']);
    }

    public function destroyAssignment(Request $request, int $assignmentId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.assignments.manage')) {
            return response()->json(['message' => 'Only authorized faculty can manage assignments.'], 403);
        }

        $assignment = DB::table('faculty_assignments')->where('id', $assignmentId)->first();
        if (! $assignment) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        if ($resp = $this->ensureVisibleOffering($user, (int) $assignment->offering_id, false)) {
            return $resp;
        }

        DB::table('faculty_assignments')->where('id', $assignmentId)->delete();

        return response()->json(['message' => 'Assignment removed successfully.']);
    }

    public function gradeSheets(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.grade_sheets.view')) {
            return response()->json(['message' => 'You do not have access to grade sheets.'], 403);
        }

        $periodId = (int) ($request->query('period_id') ?: $this->activePeriodId());
        $offerings = $this->visibleOfferingsQuery($user, $periodId, false)->get();
        $offeringIds = $offerings->pluck('id')->map(fn ($value) => (int) $value)->all();

        $gradeStats = DB::table('faculty_grade_entries')
            ->whereIn('offering_id', $offeringIds ?: [0])
            ->select(
                'offering_id',
                DB::raw('COUNT(*) as total_rows'),
                DB::raw("SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) as posted_rows"),
                DB::raw('MAX(posted_at) as latest_posted_at')
            )
            ->groupBy('offering_id')
            ->get()
            ->keyBy('offering_id');

        return response()->json([
            'items' => $offerings->map(function ($row) use ($gradeStats) {
                $stats = $gradeStats->get($row->id);

                return [
                    'offering_id' => (int) $row->id,
                    'course_code' => (string) $row->course_code,
                    'course_title' => (string) $row->course_title,
                    'section_code' => (string) ($row->section_code ?? ''),
                    'schedule_text' => (string) ($row->schedule_text ?? ''),
                    'total_rows' => $stats ? (int) $stats->total_rows : 0,
                    'posted_rows' => $stats ? (int) $stats->posted_rows : 0,
                    'latest_posted_at' => $stats?->latest_posted_at,
                ];
            })->values(),
            'can_post' => $this->can($user, 'faculty.grade_sheets.post'),
            'can_manage' => $this->can($user, 'faculty.grades.manage'),
        ]);
    }

    public function gradeSheetDetail(Request $request, int $offeringId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.grade_sheets.view')) {
            return response()->json(['message' => 'You do not have access to grade sheets.'], 403);
        }

        if ($resp = $this->ensureVisibleOffering($user, $offeringId, false)) {
            return $resp;
        }

        $rows = DB::table('enrollments as e')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->leftJoin('faculty_grade_entries as fge', function ($join) use ($offeringId) {
                $join->on('fge.enrollment_id', '=', 'e.id')
                    ->where('fge.offering_id', '=', $offeringId);
            })
            ->where('e.offering_id', $offeringId)
            ->whereIn('e.status', ['unofficial', 'official'])
            ->orderBy('u.name')
            ->select(
                'e.id as enrollment_id',
                'u.student_number',
                'u.name',
                'fge.midterm_grade',
                'fge.final_grade',
                'fge.re_exam_grade',
                'fge.status',
                'fge.posted_at'
            )
            ->get();

        return response()->json([
            'items' => $rows->map(fn ($row) => [
                'enrollment_id' => (int) $row->enrollment_id,
                'student_number' => (string) ($row->student_number ?? ''),
                'name' => (string) $row->name,
                'midterm_grade' => $row->midterm_grade !== null ? (float) $row->midterm_grade : null,
                'final_grade' => $row->final_grade !== null ? (float) $row->final_grade : null,
                're_exam_grade' => $row->re_exam_grade !== null ? (float) $row->re_exam_grade : null,
                'status' => (string) ($row->status ?? 'draft'),
                'posted_at' => $row->posted_at,
            ])->values(),
            'grading_system' => $this->gradingSystem(),
            'can_post' => $this->can($user, 'faculty.grade_sheets.post'),
            'can_manage' => $this->can($user, 'faculty.grades.manage'),
        ]);
    }

    public function saveGradeSheet(Request $request, int $offeringId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.grades.manage')) {
            return response()->json(['message' => 'Only authorized faculty can edit grade sheets.'], 403);
        }

        if ($resp = $this->ensureVisibleOffering($user, $offeringId, false)) {
            return $resp;
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'items.*.midterm_grade' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'items.*.final_grade' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'items.*.re_exam_grade' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ]);

        foreach ($validated['items'] as $row) {
            DB::table('faculty_grade_entries')->updateOrInsert(
                [
                    'offering_id' => $offeringId,
                    'enrollment_id' => (int) $row['enrollment_id'],
                ],
                [
                    'midterm_grade' => $row['midterm_grade'] ?? null,
                    'final_grade' => $row['final_grade'] ?? null,
                    're_exam_grade' => $row['re_exam_grade'] ?? null,
                    'status' => 'draft',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return response()->json(['message' => 'Grade sheet saved successfully.']);
    }

    public function postGradeSheet(Request $request, int $offeringId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.grade_sheets.post')) {
            return response()->json(['message' => 'Only authorized faculty can post grade sheets.'], 403);
        }

        if ($resp = $this->ensureVisibleOffering($user, $offeringId, false)) {
            return $resp;
        }

        $entries = DB::table('faculty_grade_entries as fge')
            ->join('enrollments as e', 'e.id', '=', 'fge.enrollment_id')
            ->where('fge.offering_id', $offeringId)
            ->select('fge.enrollment_id', 'e.user_id', 'e.course_id', 'fge.midterm_grade', 'fge.final_grade', 'fge.re_exam_grade')
            ->get();

        foreach ($entries as $entry) {
            $finalValue = $entry->re_exam_grade ?? $entry->final_grade ?? $entry->midterm_grade;
            $status = $finalValue === null ? 'incomplete' : ((float) $finalValue <= 3.00 ? 'passed' : 'failed');

            DB::table('student_course_results')->updateOrInsert(
                [
                    'user_id' => (int) $entry->user_id,
                    'course_id' => (int) $entry->course_id,
                ],
                [
                    'grade' => $finalValue,
                    'status' => $status,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('faculty_grade_entries')
            ->where('offering_id', $offeringId)
            ->update([
                'status' => 'posted',
                'posted_by_user_id' => $user->id,
                'posted_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Grade sheet posted successfully.']);
    }

    public function grades(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.grades.view')) {
            return response()->json(['message' => 'You do not have access to grades.'], 403);
        }

        $periodId = (int) ($request->query('period_id') ?: $this->activePeriodId());
        $offeringIds = $this->visibleOfferingIds($user, $periodId, false);
        $rows = DB::table('faculty_grade_entries as fge')
            ->join('enrollments as e', 'e.id', '=', 'fge.enrollment_id')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->join('class_offerings as o', 'o.id', '=', 'fge.offering_id')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->whereIn('fge.offering_id', $offeringIds ?: [0])
            ->orderBy('u.name')
            ->select(
                'fge.id',
                'fge.enrollment_id',
                'fge.offering_id',
                'fge.midterm_grade',
                'fge.final_grade',
                'fge.re_exam_grade',
                'fge.status',
                'u.name',
                'u.student_number',
                'c.code as course_code',
                'c.title as course_title'
            )
            ->get();

        return response()->json([
            'items' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'enrollment_id' => (int) $row->enrollment_id,
                'offering_id' => (int) $row->offering_id,
                'student_name' => (string) $row->name,
                'student_number' => (string) ($row->student_number ?? ''),
                'course_code' => (string) $row->course_code,
                'course_title' => (string) $row->course_title,
                'midterm_grade' => $row->midterm_grade !== null ? (float) $row->midterm_grade : null,
                'final_grade' => $row->final_grade !== null ? (float) $row->final_grade : null,
                're_exam_grade' => $row->re_exam_grade !== null ? (float) $row->re_exam_grade : null,
                'status' => (string) $row->status,
            ])->values(),
            'can_manage' => $this->can($user, 'faculty.grades.manage'),
        ]);
    }

    private function gradingSystem(): array
    {
        return [
            ['grade' => '1.00', 'equiv' => '98-100%', 'desc' => 'Excellent', 'remarks' => 'Passed'],
            ['grade' => '1.25', 'equiv' => '95-97%', 'desc' => 'Excellent', 'remarks' => 'Passed'],
            ['grade' => '1.50', 'equiv' => '92-94%', 'desc' => 'Very Good', 'remarks' => 'Passed'],
            ['grade' => '1.75', 'equiv' => '89-91%', 'desc' => 'Very Good', 'remarks' => 'Passed'],
            ['grade' => '2.00', 'equiv' => '86-88%', 'desc' => 'Good', 'remarks' => 'Passed'],
            ['grade' => '2.25', 'equiv' => '83-85%', 'desc' => 'Good', 'remarks' => 'Passed'],
            ['grade' => '2.50', 'equiv' => '80-82%', 'desc' => 'Satisfactory', 'remarks' => 'Passed'],
            ['grade' => '2.75', 'equiv' => '78-79%', 'desc' => 'Satisfactory', 'remarks' => 'Passed'],
            ['grade' => '3.00', 'equiv' => '75%', 'desc' => 'Passing', 'remarks' => 'Passed'],
            ['grade' => '5.00', 'equiv' => 'Below 75%', 'desc' => 'Failed', 'remarks' => 'Failed'],
            ['grade' => 'INC', 'equiv' => '', 'desc' => 'Incomplete', 'remarks' => 'Incomplete'],
        ];
    }

    private function formatScheduleText(string $day, string $start, string $end): string
    {
        $startLabel = date('h:i A', strtotime("1970-01-01 {$start}"));
        $endLabel = date('h:i A', strtotime("1970-01-01 {$end}"));

        return "{$day} {$startLabel} - {$endLabel}";
    }
}
