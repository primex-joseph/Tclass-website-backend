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
    private const ROOM_ICON_PRESETS = [
        'building-2',
        'door-open',
        'school',
        'monitor',
        'flask-conical',
        'microscope',
        'cpu',
        'book-open',
        'library',
        'presentation',
        'users',
        'graduation-cap',
    ];

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

    private function parseBooleanQuery(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function visibleOfferingsQuery(User $user, ?int $periodId, bool $includeAllForRegistrar = false, array $filters = [])
    {
        $context = $this->facultyContext($user);
        $teacherId = $context['teacher_id'];
        $isRegistrar = $includeAllForRegistrar && $this->can($user, 'faculty.schedules.manage');
        $search = trim((string) ($filters['search'] ?? ''));
        $filterTeacherId = isset($filters['teacher_id']) && $filters['teacher_id'] !== 'all'
            ? (int) $filters['teacher_id']
            : null;
        $filterSectionId = isset($filters['section_id']) && $filters['section_id'] !== 'all'
            ? (int) $filters['section_id']
            : null;
        $filterRoomId = isset($filters['room_id']) && $filters['room_id'] !== 'all'
            ? (int) $filters['room_id']
            : null;

        $query = DB::table('class_offerings as o')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('enrollment_periods as p', 'p.id', '=', 'o.period_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->leftJoin('users as uu', 'uu.id', '=', 'o.updated_by_user_id')
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
                'o.updated_at',
                'o.updated_by_user_id',
                'p.name as period_name',
                'c.code as course_code',
                'c.title as course_title',
                'c.units',
                'ss.section_code',
                'st.full_name as teacher_name',
                'sr.room_code',
                'uu.name as updated_by_name'
            )
            ->orderBy('o.day_of_week')
            ->orderBy('o.start_time')
            ->orderBy('c.code');

        if ($filterTeacherId) {
            $query->where('o.teacher_id', $filterTeacherId);
        }
        if ($filterSectionId) {
            $query->where('o.section_id', $filterSectionId);
        }
        if ($filterRoomId) {
            $query->where('o.room_id', $filterRoomId);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('c.code', 'like', "%{$search}%")
                    ->orWhere('c.title', 'like', "%{$search}%")
                    ->orWhere('ss.section_code', 'like', "%{$search}%")
                    ->orWhere('st.full_name', 'like', "%{$search}%")
                    ->orWhere('sr.room_code', 'like', "%{$search}%");
            });
        }

        if (! $isRegistrar) {
            if (! $teacherId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('o.teacher_id', $teacherId);
            }
        }

        return $query;
    }

    private function ensureScheduleManager(User $user): ?JsonResponse
    {
        if (! $this->can($user, 'faculty.schedules.manage')) {
            return response()->json(['message' => 'Only registrar-level faculty can manage this schedule workflow.'], 403);
        }

        return null;
    }

    private function activeEnrollmentStatuses(): array
    {
        return ['draft', 'unofficial', 'official'];
    }

    private function detectScheduleConflicts(array $payload, ?int $excludeId = null): array
    {
        $base = DB::table('class_offerings as o')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->where('o.period_id', (int) $payload['period_id'])
            ->where('o.day_of_week', (string) $payload['day_of_week'])
            ->where('o.start_time', '<', (string) $payload['end_time'])
            ->where('o.end_time', '>', (string) $payload['start_time']);

        if ($excludeId) {
            $base->where('o.id', '!=', $excludeId);
        }

        $errors = [];

        $roomConflict = (clone $base)
            ->where('o.room_id', (int) $payload['room_id'])
            ->select('c.code', 'c.title', 'o.day_of_week', 'o.start_time', 'o.end_time', 'sr.room_code')
            ->first();
        if ($roomConflict) {
            $startLabel = date('h:i A', strtotime("1970-01-01 {$roomConflict->start_time}"));
            $endLabel = date('h:i A', strtotime("1970-01-01 {$roomConflict->end_time}"));
            $errors[] = "Room conflict: {$roomConflict->room_code} is already used every {$roomConflict->day_of_week} at {$startLabel}-{$endLabel} ({$roomConflict->code} - {$roomConflict->title}).";
        }

        $teacherConflict = (clone $base)
            ->where('o.teacher_id', (int) $payload['teacher_id'])
            ->select('c.code', 'c.title', 'o.day_of_week', 'o.start_time', 'o.end_time', 'st.full_name')
            ->first();
        if ($teacherConflict) {
            $errors[] = "Teacher conflict: {$teacherConflict->full_name} already has {$teacherConflict->code} - {$teacherConflict->title} ({$teacherConflict->day_of_week} {$teacherConflict->start_time}-{$teacherConflict->end_time}).";
        }

        $sectionConflict = (clone $base)
            ->where('o.section_id', (int) $payload['section_id'])
            ->select('c.code', 'c.title', 'o.day_of_week', 'o.start_time', 'o.end_time', 'ss.section_code')
            ->first();
        if ($sectionConflict) {
            $errors[] = "Section conflict: {$sectionConflict->section_code} already has {$sectionConflict->code} - {$sectionConflict->title} ({$sectionConflict->day_of_week} {$sectionConflict->start_time}-{$sectionConflict->end_time}).";
        }

        return $errors;
    }

    private function writeEnrollmentActionLog(array $payload): void
    {
        DB::table('enrollment_action_logs')->insert([
            'enrollment_id' => $payload['enrollment_id'] ?? null,
            'offering_id' => $payload['offering_id'] ?? null,
            'student_user_id' => $payload['student_user_id'] ?? null,
            'acted_by_user_id' => $payload['acted_by_user_id'] ?? null,
            'action' => $payload['action'],
            'from_status' => $payload['from_status'] ?? null,
            'to_status' => $payload['to_status'] ?? null,
            'note' => $payload['note'] ?? null,
            'acted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function writeSchedulingActionLog(array $payload): void
    {
        DB::table('scheduling_action_logs')->insert([
            'entity_type' => $payload['entity_type'],
            'entity_id' => (int) $payload['entity_id'],
            'action' => (string) $payload['action'],
            'acted_by_user_id' => $payload['acted_by_user_id'] ?? null,
            'room_id' => $payload['room_id'] ?? null,
            'offering_id' => $payload['offering_id'] ?? null,
            'period_id' => $payload['period_id'] ?? null,
            'before_snapshot' => array_key_exists('before_snapshot', $payload)
                ? json_encode($payload['before_snapshot'], JSON_UNESCAPED_UNICODE)
                : null,
            'after_snapshot' => array_key_exists('after_snapshot', $payload)
                ? json_encode($payload['after_snapshot'], JSON_UNESCAPED_UNICODE)
                : null,
            'note' => $payload['note'] ?? null,
            'acted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roomPayloadById(int $roomId): ?array
    {
        $row = DB::table('schedule_rooms as sr')
            ->leftJoin('users as cu', 'cu.id', '=', 'sr.created_by_user_id')
            ->leftJoin('users as uu', 'uu.id', '=', 'sr.updated_by_user_id')
            ->where('sr.id', $roomId)
            ->select(
                'sr.id',
                'sr.room_code',
                'sr.title',
                'sr.description',
                'sr.icon_key',
                'sr.building',
                'sr.capacity',
                'sr.is_active',
                'sr.created_at',
                'sr.updated_at',
                'sr.created_by_user_id',
                'sr.updated_by_user_id',
                'cu.name as created_by_name',
                'uu.name as updated_by_name'
            )
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'room_code' => (string) $row->room_code,
            'title' => $row->title ? (string) $row->title : null,
            'description' => $row->description ? (string) $row->description : null,
            'icon_key' => $row->icon_key ? (string) $row->icon_key : null,
            'building' => $row->building ? (string) $row->building : null,
            'capacity' => $row->capacity !== null ? (int) $row->capacity : null,
            'is_active' => (bool) $row->is_active,
            'created_at' => $row->created_at ? (string) $row->created_at : null,
            'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
            'created_by_user_id' => $row->created_by_user_id !== null ? (int) $row->created_by_user_id : null,
            'updated_by_user_id' => $row->updated_by_user_id !== null ? (int) $row->updated_by_user_id : null,
            'created_by_name' => $row->created_by_name ? (string) $row->created_by_name : null,
            'updated_by_name' => $row->updated_by_name ? (string) $row->updated_by_name : null,
        ];
    }

    private function latestEnrollmentActionMap(array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        $latestRows = DB::table('enrollment_action_logs as eal')
            ->leftJoin('users as actor', 'actor.id', '=', 'eal.acted_by_user_id')
            ->joinSub(
                DB::table('enrollment_action_logs')
                    ->selectRaw('MAX(id) as id')
                    ->whereIn('enrollment_id', $enrollmentIds)
                    ->groupBy('enrollment_id'),
                'latest',
                fn ($join) => $join->on('latest.id', '=', 'eal.id')
            )
            ->select(
                'eal.enrollment_id',
                'eal.action',
                'eal.from_status',
                'eal.to_status',
                'eal.note',
                'eal.acted_at',
                'actor.name as acted_by_name'
            )
            ->get();

        $map = [];
        foreach ($latestRows as $row) {
            $map[(int) $row->enrollment_id] = [
                'action' => (string) $row->action,
                'from_status' => $row->from_status ? (string) $row->from_status : null,
                'to_status' => $row->to_status ? (string) $row->to_status : null,
                'note' => $row->note ? (string) $row->note : null,
                'acted_at' => $row->acted_at ? (string) $row->acted_at : null,
                'acted_by_name' => $row->acted_by_name ? (string) $row->acted_by_name : null,
            ];
        }

        return $map;
    }

    private function countActiveOfferEnrollment(int $offeringId, ?int $excludeEnrollmentId = null): int
    {
        return DB::table('enrollments')
            ->where('offering_id', $offeringId)
            ->whereIn('status', $this->activeEnrollmentStatuses())
            ->when($excludeEnrollmentId, fn ($query) => $query->where('id', '!=', $excludeEnrollmentId))
            ->count();
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
        $filters = [
            'teacher_id' => $request->query('teacher_id'),
            'section_id' => $request->query('section_id'),
            'room_id' => $request->query('room_id'),
            'search' => $request->query('search'),
        ];
        $offerings = $this->visibleOfferingsQuery($user, $periodId, true, $filters)->get();

        return response()->json([
            'items' => $offerings->map(fn ($row) => [
                'offering_id' => (int) $row->id,
                'period_id' => (int) $row->period_id,
                'course_id' => (int) $row->course_id,
                'course_code' => (string) $row->course_code,
                'course_title' => (string) $row->course_title,
                'section_id' => $row->section_id !== null ? (int) $row->section_id : null,
                'section_code' => (string) ($row->section_code ?? ''),
                'teacher_id' => $row->teacher_id !== null ? (int) $row->teacher_id : null,
                'teacher_name' => (string) ($row->teacher_name ?? ''),
                'room_id' => $row->room_id !== null ? (int) $row->room_id : null,
                'room_code' => (string) ($row->room_code ?? ''),
                'day_of_week' => (string) $row->day_of_week,
                'start_time' => (string) $row->start_time,
                'end_time' => (string) $row->end_time,
                'schedule_text' => (string) ($row->schedule_text ?? ''),
                'period_name' => (string) ($row->period_name ?? ''),
                'capacity' => (int) $row->capacity,
                'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
                'updated_by_user_id' => $row->updated_by_user_id !== null ? (int) $row->updated_by_user_id : null,
                'updated_by_name' => $row->updated_by_name ? (string) $row->updated_by_name : null,
            ])->values(),
            'can_manage' => $this->can($user, 'faculty.schedules.manage'),
            'can_export' => $this->can($user, 'faculty.schedules.export'),
        ]);
    }

    public function classScheduleMasters(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.schedules.view')) {
            return response()->json(['message' => 'You do not have access to schedules.'], 403);
        }

        $rooms = DB::table('schedule_rooms as sr')
            ->leftJoin('users as cu', 'cu.id', '=', 'sr.created_by_user_id')
            ->leftJoin('users as uu', 'uu.id', '=', 'sr.updated_by_user_id')
            ->where('sr.is_active', 1)
            ->orderBy('sr.room_code')
            ->select(
                'sr.id',
                'sr.room_code',
                'sr.title',
                'sr.description',
                'sr.icon_key',
                'sr.building',
                'sr.capacity',
                'sr.is_active',
                'sr.created_at',
                'sr.updated_at',
                'sr.created_by_user_id',
                'sr.updated_by_user_id',
                'cu.name as created_by_name',
                'uu.name as updated_by_name'
            )
            ->get();

        return response()->json([
            'teachers' => DB::table('schedule_teachers')
                ->where('is_active', 1)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'email']),
            'rooms' => $rooms,
            'sections' => DB::table('schedule_sections')
                ->where('is_active', 1)
                ->orderBy('section_code')
                ->get(['id', 'section_code', 'program_name', 'year_level']),
            'can_manage' => $this->can($user, 'faculty.schedules.manage'),
            'room_icon_presets' => self::ROOM_ICON_PRESETS,
        ]);
    }

    public function classScheduleRooms(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if ($resp = $this->ensureScheduleManager($user)) {
            return $resp;
        }

        $search = trim((string) $request->query('search', ''));
        $activeOnly = $this->parseBooleanQuery($request->query('active_only'));
        $building = trim((string) $request->query('building', ''));
        $capacityMin = $request->query('capacity_min');
        $capacityMax = $request->query('capacity_max');

        $query = DB::table('schedule_rooms as sr')
            ->leftJoin('users as cu', 'cu.id', '=', 'sr.created_by_user_id')
            ->leftJoin('users as uu', 'uu.id', '=', 'sr.updated_by_user_id')
            ->select(
                'sr.id',
                'sr.room_code',
                'sr.title',
                'sr.description',
                'sr.icon_key',
                'sr.building',
                'sr.capacity',
                'sr.is_active',
                'sr.created_at',
                'sr.updated_at',
                'sr.created_by_user_id',
                'sr.updated_by_user_id',
                'cu.name as created_by_name',
                'uu.name as updated_by_name'
            )
            ->orderBy('sr.room_code');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('sr.room_code', 'like', "%{$search}%")
                    ->orWhere('sr.title', 'like', "%{$search}%")
                    ->orWhere('sr.description', 'like', "%{$search}%")
                    ->orWhere('sr.building', 'like', "%{$search}%");
            });
        }
        if ($activeOnly !== null) {
            $query->where('sr.is_active', $activeOnly ? 1 : 0);
        }
        if ($building !== '') {
            $query->where('sr.building', 'like', "%{$building}%");
        }
        if ($capacityMin !== null && is_numeric((string) $capacityMin)) {
            $query->where('sr.capacity', '>=', (int) $capacityMin);
        }
        if ($capacityMax !== null && is_numeric((string) $capacityMax)) {
            $query->where('sr.capacity', '<=', (int) $capacityMax);
        }

        return response()->json([
            'items' => $query->get(),
            'room_icon_presets' => self::ROOM_ICON_PRESETS,
        ]);
    }

    public function storeClassScheduleRoom(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if ($resp = $this->ensureScheduleManager($user)) {
            return $resp;
        }

        $validated = $request->validate([
            'room_code' => ['required', 'string', 'max:80', 'unique:schedule_rooms,room_code'],
            'title' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:1200'],
            'icon_key' => ['nullable', 'string', 'max:80'],
            'building' => ['nullable', 'string', 'max:80'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $roomId = (int) DB::table('schedule_rooms')->insertGetId([
            'room_code' => strtoupper(trim((string) $validated['room_code'])),
            'title' => trim((string) $validated['title']),
            'description' => $validated['description'] ?? null,
            'icon_key' => $validated['icon_key'] ?? null,
            'building' => $validated['building'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomPayload = $this->roomPayloadById($roomId);
        $this->writeSchedulingActionLog([
            'entity_type' => 'room',
            'entity_id' => $roomId,
            'action' => 'create',
            'acted_by_user_id' => $user->id,
            'room_id' => $roomId,
            'after_snapshot' => $roomPayload,
        ]);

        return response()->json([
            'message' => 'Room created successfully.',
            'item' => $roomPayload,
        ], 201);
    }

    public function updateClassScheduleRoom(Request $request, int $roomId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if ($resp = $this->ensureScheduleManager($user)) {
            return $resp;
        }

        $room = DB::table('schedule_rooms')->where('id', $roomId)->first();
        if (! $room) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $validated = $request->validate([
            'room_code' => ['sometimes', 'string', 'max:80', 'unique:schedule_rooms,room_code,' . $roomId],
            'title' => ['sometimes', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:1200'],
            'icon_key' => ['nullable', 'string', 'max:80'],
            'building' => ['nullable', 'string', 'max:80'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $update = [];
        foreach (['room_code', 'title', 'description', 'icon_key', 'building', 'capacity', 'is_active'] as $field) {
            if (array_key_exists($field, $validated)) {
                $update[$field] = $field === 'room_code'
                    ? strtoupper(trim((string) $validated[$field]))
                    : $validated[$field];
            }
        }
        $update['updated_by_user_id'] = $user->id;
        $update['updated_at'] = now();

        DB::table('schedule_rooms')->where('id', $roomId)->update($update);
        $updatedRoomPayload = $this->roomPayloadById($roomId);
        $this->writeSchedulingActionLog([
            'entity_type' => 'room',
            'entity_id' => $roomId,
            'action' => 'update',
            'acted_by_user_id' => $user->id,
            'room_id' => $roomId,
            'before_snapshot' => $room,
            'after_snapshot' => $updatedRoomPayload,
        ]);

        return response()->json([
            'message' => 'Room updated successfully.',
            'item' => $updatedRoomPayload,
        ]);
    }

    public function destroyClassScheduleRoom(Request $request, int $roomId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if ($resp = $this->ensureScheduleManager($user)) {
            return $resp;
        }

        $room = DB::table('schedule_rooms')->where('id', $roomId)->first();
        if (! $room) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $offeringIds = DB::table('class_offerings')->where('room_id', $roomId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $impact = [
            'offering_count' => count($offeringIds),
            'affected_enrollment_count' => DB::table('enrollments')->whereIn('offering_id', $offeringIds ?: [0])->count(),
        ];

        if ($request->boolean('preview')) {
            return response()->json([
                'room_id' => $roomId,
                'room_code' => (string) $room->room_code,
                'requires_confirmation' => true,
                'impact' => $impact,
                'message' => 'Room delete will cascade linked offerings. Submit force confirmation to continue.',
            ]);
        }

        $validated = $request->validate([
            'confirm_force' => ['required', 'accepted'],
            'confirm_text' => ['required', 'string'],
        ]);

        if (strtoupper(trim((string) $validated['confirm_text'])) !== strtoupper((string) $room->room_code)) {
            return response()->json([
                'message' => 'Confirmation text mismatch. Type the exact room code before deleting.',
                'impact' => $impact,
            ], 422);
        }

        $cascadeOfferings = DB::table('class_offerings')->whereIn('id', $offeringIds ?: [0])->get();
        foreach ($cascadeOfferings as $offering) {
            $this->writeSchedulingActionLog([
                'entity_type' => 'offering',
                'entity_id' => (int) $offering->id,
                'action' => 'delete_cascade_room',
                'acted_by_user_id' => $user->id,
                'room_id' => $roomId,
                'offering_id' => (int) $offering->id,
                'period_id' => (int) $offering->period_id,
                'before_snapshot' => $offering,
                'note' => "Cascade delete from room {$room->room_code}",
            ]);
        }

        DB::table('schedule_rooms')->where('id', $roomId)->delete();
        $this->writeSchedulingActionLog([
            'entity_type' => 'room',
            'entity_id' => $roomId,
            'action' => 'delete',
            'acted_by_user_id' => $user->id,
            'room_id' => null,
            'before_snapshot' => $room,
            'note' => "Cascade offerings: {$impact['offering_count']}",
        ]);

        return response()->json(['message' => 'Room deleted successfully. Linked offerings were cascaded.', 'impact' => $impact]);
    }

    public function classScheduleRoomAvailability(Request $request): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if (! $this->can($user, 'faculty.schedules.view')) {
            return response()->json(['message' => 'You do not have access to schedules.'], 403);
        }

        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:enrollment_periods,id'],
            'day_of_week' => ['nullable', 'in:Mon,Tue,Wed,Thu,Fri,Sat'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'search' => ['nullable', 'string', 'max:120'],
            'active_only' => ['nullable', 'boolean'],
            'building' => ['nullable', 'string', 'max:80'],
            'capacity_min' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'capacity_max' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'exclude_offering_id' => ['nullable', 'integer', 'exists:class_offerings,id'],
        ]);

        $rooms = DB::table('schedule_rooms as sr')
            ->leftJoin('users as cu', 'cu.id', '=', 'sr.created_by_user_id')
            ->leftJoin('users as uu', 'uu.id', '=', 'sr.updated_by_user_id')
            ->when(! empty($validated['search']), function ($query) use ($validated) {
                $search = trim((string) $validated['search']);
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('sr.room_code', 'like', "%{$search}%")
                        ->orWhere('sr.title', 'like', "%{$search}%")
                        ->orWhere('sr.description', 'like', "%{$search}%")
                        ->orWhere('sr.building', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists('active_only', $validated), fn ($query) => $query->where('sr.is_active', (bool) $validated['active_only'] ? 1 : 0))
            ->when(! empty($validated['building']), fn ($query) => $query->where('sr.building', 'like', '%' . trim((string) $validated['building']) . '%'))
            ->when(! empty($validated['capacity_min']), fn ($query) => $query->where('sr.capacity', '>=', (int) $validated['capacity_min']))
            ->when(! empty($validated['capacity_max']), fn ($query) => $query->where('sr.capacity', '<=', (int) $validated['capacity_max']))
            ->orderBy('sr.room_code')
            ->select(
                'sr.id',
                'sr.room_code',
                'sr.title',
                'sr.description',
                'sr.icon_key',
                'sr.building',
                'sr.capacity',
                'sr.is_active',
                'sr.created_at',
                'sr.updated_at',
                'sr.created_by_user_id',
                'sr.updated_by_user_id',
                'cu.name as created_by_name',
                'uu.name as updated_by_name'
            )
            ->get();

        $hasSlot = ! empty($validated['period_id']) && ! empty($validated['day_of_week']) && ! empty($validated['start_time']) && ! empty($validated['end_time']);
        if (! $hasSlot) {
            return response()->json([
                'has_slot' => false,
                'items' => $rooms->map(fn ($room) => [
                    'room_id' => (int) $room->id,
                    'room_code' => (string) $room->room_code,
                    'title' => $room->title ? (string) $room->title : null,
                    'description' => $room->description ? (string) $room->description : null,
                    'icon_key' => $room->icon_key ? (string) $room->icon_key : null,
                    'building' => $room->building ? (string) $room->building : null,
                    'capacity' => $room->capacity !== null ? (int) $room->capacity : null,
                    'is_active' => (bool) $room->is_active,
                    'created_at' => $room->created_at ? (string) $room->created_at : null,
                    'updated_at' => $room->updated_at ? (string) $room->updated_at : null,
                    'created_by_user_id' => $room->created_by_user_id !== null ? (int) $room->created_by_user_id : null,
                    'updated_by_user_id' => $room->updated_by_user_id !== null ? (int) $room->updated_by_user_id : null,
                    'created_by_name' => $room->created_by_name ? (string) $room->created_by_name : null,
                    'updated_by_name' => $room->updated_by_name ? (string) $room->updated_by_name : null,
                    'is_available' => true,
                    'warnings' => [],
                    'conflicts' => [],
                ])->values(),
            ]);
        }

        $conflicts = DB::table('class_offerings as o')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->where('o.period_id', (int) $validated['period_id'])
            ->where('o.day_of_week', (string) $validated['day_of_week'])
            ->where('o.start_time', '<', $validated['end_time'] . ':00')
            ->where('o.end_time', '>', $validated['start_time'] . ':00')
            ->when(! empty($validated['exclude_offering_id']), fn ($query) => $query->where('o.id', '!=', (int) $validated['exclude_offering_id']))
            ->select('o.id as offering_id', 'o.room_id', 'o.day_of_week', 'o.start_time', 'o.end_time', 'c.code as course_code', 'c.title as course_title', 'sr.room_code')
            ->get()
            ->groupBy('room_id');

        $items = $rooms->map(function ($room) use ($conflicts) {
            $roomConflicts = $conflicts->get($room->id, collect());
            $warnings = $roomConflicts->map(function ($entry) {
                $start = date('h:i A', strtotime("1970-01-01 {$entry->start_time}"));
                $end = date('h:i A', strtotime("1970-01-01 {$entry->end_time}"));
                return "{$entry->room_code} is already used every {$entry->day_of_week} at {$start}-{$end} ({$entry->course_code} - {$entry->course_title})";
            })->values()->all();

            return [
                'room_id' => (int) $room->id,
                'room_code' => (string) $room->room_code,
                'title' => $room->title ? (string) $room->title : null,
                'description' => $room->description ? (string) $room->description : null,
                'icon_key' => $room->icon_key ? (string) $room->icon_key : null,
                'building' => $room->building ? (string) $room->building : null,
                'capacity' => $room->capacity !== null ? (int) $room->capacity : null,
                'is_active' => (bool) $room->is_active,
                'created_at' => $room->created_at ? (string) $room->created_at : null,
                'updated_at' => $room->updated_at ? (string) $room->updated_at : null,
                'created_by_user_id' => $room->created_by_user_id !== null ? (int) $room->created_by_user_id : null,
                'updated_by_user_id' => $room->updated_by_user_id !== null ? (int) $room->updated_by_user_id : null,
                'created_by_name' => $room->created_by_name ? (string) $room->created_by_name : null,
                'updated_by_name' => $room->updated_by_name ? (string) $room->updated_by_name : null,
                'is_available' => $roomConflicts->isEmpty(),
                'warnings' => $warnings,
                'conflicts' => $roomConflicts->map(fn ($entry) => [
                    'offering_id' => (int) $entry->offering_id,
                    'course_code' => (string) $entry->course_code,
                    'course_title' => (string) $entry->course_title,
                    'day_of_week' => (string) $entry->day_of_week,
                    'start_time' => (string) $entry->start_time,
                    'end_time' => (string) $entry->end_time,
                ])->values()->all(),
            ];
        })->values();

        return response()->json(['has_slot' => true, 'items' => $items]);
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

        $allowRegistrarAll = $this->can($user, 'faculty.schedules.manage');
        if ($resp = $this->ensureVisibleOffering($user, $offeringId, $allowRegistrarAll)) {
            return $resp;
        }

        $offering = DB::table('class_offerings')->where('id', $offeringId)->first();
        if (! $offering) {
            return response()->json(['message' => 'Class offering not found.'], 404);
        }

        $validated = $request->validate([
            'day_of_week' => ['required', 'in:Mon,Tue,Wed,Thu,Fri,Sat'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room_id' => ['nullable', 'integer', 'exists:schedule_rooms,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:schedule_teachers,id'],
            'section_id' => ['nullable', 'integer', 'exists:schedule_sections,id'],
        ]);

        $nextPayload = [
            'period_id' => (int) $offering->period_id,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'] . ':00',
            'end_time' => $validated['end_time'] . ':00',
            'room_id' => (int) ($validated['room_id'] ?? $offering->room_id),
            'teacher_id' => (int) ($validated['teacher_id'] ?? $offering->teacher_id),
            'section_id' => (int) ($validated['section_id'] ?? $offering->section_id),
        ];

        if (! $nextPayload['room_id'] || ! $nextPayload['teacher_id'] || ! $nextPayload['section_id']) {
            return response()->json(['message' => 'Schedule update requires room, teacher, and section values.'], 422);
        }

        $conflicts = $this->detectScheduleConflicts($nextPayload, $offeringId);
        if (! empty($conflicts)) {
            return response()->json([
                'message' => 'Schedule conflict detected.',
                'errors' => ['conflict' => $conflicts],
            ], 422);
        }

        $payload = array_filter([
            'day_of_week' => $nextPayload['day_of_week'],
            'start_time' => $nextPayload['start_time'],
            'end_time' => $nextPayload['end_time'],
            'room_id' => $nextPayload['room_id'],
            'teacher_id' => $nextPayload['teacher_id'],
            'section_id' => $nextPayload['section_id'],
            'schedule_text' => $this->formatScheduleText($validated['day_of_week'], $validated['start_time'], $validated['end_time']),
            'updated_by_user_id' => $user->id,
            'updated_at' => now(),
        ]);

        $beforeSnapshot = [
            'id' => (int) $offering->id,
            'period_id' => (int) $offering->period_id,
            'course_id' => (int) $offering->course_id,
            'section_id' => (int) $offering->section_id,
            'teacher_id' => (int) $offering->teacher_id,
            'room_id' => (int) $offering->room_id,
            'day_of_week' => (string) $offering->day_of_week,
            'start_time' => (string) $offering->start_time,
            'end_time' => (string) $offering->end_time,
            'capacity' => (int) $offering->capacity,
            'is_active' => (bool) $offering->is_active,
        ];

        DB::table('class_offerings')->where('id', $offeringId)->update($payload);

        $updatedOffering = DB::table('class_offerings')->where('id', $offeringId)->first();
        $afterSnapshot = $updatedOffering ? [
            'id' => (int) $updatedOffering->id,
            'period_id' => (int) $updatedOffering->period_id,
            'course_id' => (int) $updatedOffering->course_id,
            'section_id' => (int) $updatedOffering->section_id,
            'teacher_id' => (int) $updatedOffering->teacher_id,
            'room_id' => (int) $updatedOffering->room_id,
            'day_of_week' => (string) $updatedOffering->day_of_week,
            'start_time' => (string) $updatedOffering->start_time,
            'end_time' => (string) $updatedOffering->end_time,
            'capacity' => (int) $updatedOffering->capacity,
            'is_active' => (bool) $updatedOffering->is_active,
        ] : null;

        $this->writeSchedulingActionLog([
            'entity_type' => 'offering',
            'entity_id' => $offeringId,
            'action' => 'update',
            'acted_by_user_id' => $user->id,
            'offering_id' => $offeringId,
            'room_id' => $nextPayload['room_id'],
            'period_id' => $nextPayload['period_id'],
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
        ]);

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

    public function offeringStudents(Request $request, int $offeringId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if ($resp = $this->ensureScheduleManager($user)) {
            return $resp;
        }
        if ($resp = $this->ensureVisibleOffering($user, $offeringId, true)) {
            return $resp;
        }

        $offering = DB::table('class_offerings as o')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->where('o.id', $offeringId)
            ->select(
                'o.id',
                'o.period_id',
                'o.course_id',
                'o.capacity',
                'c.code as course_code',
                'c.title as course_title',
                'ss.section_code',
                'st.full_name as teacher_name',
                'sr.room_code',
                'o.schedule_text'
            )
            ->first();
        if (! $offering) {
            return response()->json(['message' => 'Class offering not found.'], 404);
        }

        $rows = DB::table('enrollments as e')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.offering_id', $offeringId)
            ->whereIn('e.status', $this->activeEnrollmentStatuses())
            ->orderBy('u.name')
            ->select(
                'e.id as enrollment_id',
                'e.user_id',
                'e.status',
                'e.requested_at',
                'e.assessed_at',
                'e.decided_at',
                'e.remarks',
                'u.name',
                'u.email',
                'u.student_number'
            )
            ->get();

        $enrollmentIds = $rows->pluck('enrollment_id')->map(fn ($id) => (int) $id)->all();
        $latestActionMap = $this->latestEnrollmentActionMap($enrollmentIds);

        return response()->json([
            'offering' => [
                'offering_id' => (int) $offering->id,
                'period_id' => (int) $offering->period_id,
                'course_id' => (int) $offering->course_id,
                'course_code' => (string) $offering->course_code,
                'course_title' => (string) $offering->course_title,
                'section_code' => (string) ($offering->section_code ?? ''),
                'teacher_name' => (string) ($offering->teacher_name ?? ''),
                'room_code' => (string) ($offering->room_code ?? ''),
                'schedule_text' => (string) ($offering->schedule_text ?? ''),
                'capacity' => (int) $offering->capacity,
                'enrolled_count' => (int) $rows->count(),
            ],
            'items' => $rows->map(function ($row) use ($latestActionMap) {
                $latestAction = $latestActionMap[(int) $row->enrollment_id] ?? null;
                return [
                    'enrollment_id' => (int) $row->enrollment_id,
                    'student_user_id' => (int) $row->user_id,
                    'name' => (string) $row->name,
                    'email' => (string) $row->email,
                    'student_number' => (string) ($row->student_number ?? ''),
                    'status' => (string) $row->status,
                    'requested_at' => $row->requested_at ? (string) $row->requested_at : null,
                    'assessed_at' => $row->assessed_at ? (string) $row->assessed_at : null,
                    'decided_at' => $row->decided_at ? (string) $row->decided_at : null,
                    'remarks' => $row->remarks ? (string) $row->remarks : null,
                    'latest_action' => $latestAction,
                ];
            })->values(),
        ]);
    }

    public function searchStudentsForOffering(Request $request, int $offeringId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if ($resp = $this->ensureScheduleManager($user)) {
            return $resp;
        }
        if ($resp = $this->ensureVisibleOffering($user, $offeringId, true)) {
            return $resp;
        }

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['items' => []]);
        }

        $offering = DB::table('class_offerings')->where('id', $offeringId)->first(['id', 'course_id', 'period_id']);
        if (! $offering) {
            return response()->json(['message' => 'Class offering not found.'], 404);
        }

        $rows = DB::table('users as u')
            ->join('portal_user_roles as pur', function ($join) {
                $join->on('pur.user_id', '=', 'u.id')
                    ->where('pur.role', '=', 'student')
                    ->where('pur.is_active', '=', 1);
            })
            ->leftJoin('enrollments as e', function ($join) use ($offering) {
                $join->on('e.user_id', '=', 'u.id')
                    ->where('e.period_id', '=', $offering->period_id)
                    ->where('e.course_id', '=', $offering->course_id);
            })
            ->where(function ($builder) use ($query) {
                $builder
                    ->where('u.name', 'like', "%{$query}%")
                    ->orWhere('u.email', 'like', "%{$query}%")
                    ->orWhere('u.student_number', 'like', "%{$query}%");
            })
            ->orderBy('u.name')
            ->limit(20)
            ->select(
                'u.id as student_user_id',
                'u.name',
                'u.email',
                'u.student_number',
                'e.id as enrollment_id',
                'e.offering_id as existing_offering_id',
                'e.status as existing_status'
            )
            ->get();

        return response()->json([
            'items' => $rows->map(fn ($row) => [
                'student_user_id' => (int) $row->student_user_id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'student_number' => (string) ($row->student_number ?? ''),
                'enrollment_id' => $row->enrollment_id !== null ? (int) $row->enrollment_id : null,
                'existing_offering_id' => $row->existing_offering_id !== null ? (int) $row->existing_offering_id : null,
                'existing_status' => $row->existing_status ? (string) $row->existing_status : null,
            ])->values(),
        ]);
    }

    public function addStudentToOffering(Request $request, int $offeringId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if ($resp = $this->ensureScheduleManager($user)) {
            return $resp;
        }
        if ($resp = $this->ensureVisibleOffering($user, $offeringId, true)) {
            return $resp;
        }

        $validated = $request->validate([
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $studentRole = DB::table('portal_user_roles')
            ->where('user_id', (int) $validated['student_user_id'])
            ->where('role', 'student')
            ->where('is_active', 1)
            ->exists();
        if (! $studentRole) {
            return response()->json(['message' => 'Selected user is not an active student account.'], 422);
        }

        $offering = DB::table('class_offerings')->where('id', $offeringId)->first();
        if (! $offering) {
            return response()->json(['message' => 'Class offering not found.'], 404);
        }

        $existing = DB::table('enrollments')
            ->where('user_id', (int) $validated['student_user_id'])
            ->where('course_id', (int) $offering->course_id)
            ->where('period_id', (int) $offering->period_id)
            ->first();

        $fromStatus = $existing ? (string) $existing->status : null;
        if ($existing && $existing->status === 'official') {
            return response()->json(['message' => 'Student is already officially enrolled for this subject in this period.'], 422);
        }

        $count = $this->countActiveOfferEnrollment($offeringId, $existing?->id ? (int) $existing->id : null);
        if ($count >= (int) $offering->capacity) {
            return response()->json(['message' => 'Selected section is already full.'], 422);
        }

        if ($existing) {
            DB::table('enrollments')->where('id', (int) $existing->id)->update([
                'offering_id' => $offeringId,
                'status' => 'unofficial',
                'requested_at' => now(),
                'assessed_at' => now(),
                'decided_at' => null,
                'decided_by' => null,
                'remarks' => $validated['note'] ?? null,
                'updated_at' => now(),
            ]);
            $enrollmentId = (int) $existing->id;
        } else {
            $enrollmentId = (int) DB::table('enrollments')->insertGetId([
                'user_id' => (int) $validated['student_user_id'],
                'course_id' => (int) $offering->course_id,
                'period_id' => (int) $offering->period_id,
                'offering_id' => $offeringId,
                'status' => 'unofficial',
                'requested_at' => now(),
                'assessed_at' => now(),
                'remarks' => $validated['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->writeEnrollmentActionLog([
            'enrollment_id' => $enrollmentId,
            'offering_id' => $offeringId,
            'student_user_id' => (int) $validated['student_user_id'],
            'acted_by_user_id' => $user->id,
            'action' => 'add',
            'from_status' => $fromStatus,
            'to_status' => 'unofficial',
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json(['message' => 'Student added to class roster as unofficial.']);
    }

    public function updateOfferingEnrollmentStatus(Request $request, int $offeringId, int $enrollmentId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if ($resp = $this->ensureScheduleManager($user)) {
            return $resp;
        }
        if ($resp = $this->ensureVisibleOffering($user, $offeringId, true)) {
            return $resp;
        }

        $validated = $request->validate([
            'action' => ['required', 'in:verify,unverify'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $enrollment = DB::table('enrollments')
            ->where('id', $enrollmentId)
            ->where('offering_id', $offeringId)
            ->first();
        if (! $enrollment) {
            return response()->json(['message' => 'Enrollment row not found for this offering.'], 404);
        }

        $fromStatus = (string) $enrollment->status;
        if ($validated['action'] === 'verify' && $fromStatus !== 'unofficial') {
            return response()->json(['message' => 'Only unofficial rows can be verified.'], 422);
        }
        if ($validated['action'] === 'unverify' && $fromStatus !== 'official') {
            return response()->json(['message' => 'Only official rows can be unverified.'], 422);
        }

        if ($validated['action'] === 'verify') {
            DB::table('enrollments')->where('id', $enrollmentId)->update([
                'status' => 'official',
                'decided_by' => $user->id,
                'decided_at' => now(),
                'remarks' => $validated['note'] ?? $enrollment->remarks,
                'updated_at' => now(),
            ]);
            $toStatus = 'official';
        } else {
            DB::table('enrollments')->where('id', $enrollmentId)->update([
                'status' => 'unofficial',
                'decided_by' => null,
                'decided_at' => null,
                'remarks' => $validated['note'] ?? $enrollment->remarks,
                'updated_at' => now(),
            ]);
            $toStatus = 'unofficial';
        }

        $this->writeEnrollmentActionLog([
            'enrollment_id' => $enrollmentId,
            'offering_id' => $offeringId,
            'student_user_id' => (int) $enrollment->user_id,
            'acted_by_user_id' => $user->id,
            'action' => $validated['action'],
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => $validated['action'] === 'verify'
                ? 'Enrollment verified and marked official.'
                : 'Enrollment reverted to unofficial.',
        ]);
    }

    public function removeStudentFromOffering(Request $request, int $offeringId, int $enrollmentId): JsonResponse
    {
        if ($resp = $this->ensureFaculty($request)) {
            return $resp;
        }

        $user = $request->user();
        if ($resp = $this->ensureScheduleManager($user)) {
            return $resp;
        }
        if ($resp = $this->ensureVisibleOffering($user, $offeringId, true)) {
            return $resp;
        }

        $enrollment = DB::table('enrollments')
            ->where('id', $enrollmentId)
            ->where('offering_id', $offeringId)
            ->first();
        if (! $enrollment) {
            return response()->json(['message' => 'Enrollment row not found for this offering.'], 404);
        }
        if (! in_array((string) $enrollment->status, ['draft', 'unofficial'], true)) {
            return response()->json(['message' => 'Only draft or unofficial rows can be removed.'], 422);
        }

        $this->writeEnrollmentActionLog([
            'enrollment_id' => $enrollmentId,
            'offering_id' => $offeringId,
            'student_user_id' => (int) $enrollment->user_id,
            'acted_by_user_id' => $user->id,
            'action' => 'remove',
            'from_status' => (string) $enrollment->status,
            'to_status' => null,
            'note' => null,
        ]);

        DB::table('enrollments')->where('id', $enrollmentId)->delete();

        return response()->json(['message' => 'Student removed from class roster.']);
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
