<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminClassSchedulingController extends Controller
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

    private function ensureRole(int $userId, string $role): bool
    {
        return DB::table('portal_user_roles')
            ->where('user_id', $userId)
            ->where('role', $role)
            ->where('is_active', 1)
            ->exists();
    }

    private function formatScheduleText(string $day, string $start, string $end): string
    {
        $startTs = strtotime("1970-01-01 {$start}");
        $endTs = strtotime("1970-01-01 {$end}");
        $startLabel = $startTs ? date('h:i A', $startTs) : $start;
        $endLabel = $endTs ? date('h:i A', $endTs) : $end;
        return "{$day} {$startLabel} - {$endLabel}";
    }

    private function formatTimeLabel(string $value): string
    {
        $ts = strtotime("1970-01-01 {$value}");
        return $ts ? date('h:i A', $ts) : $value;
    }

    private function parseBooleanQuery(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function scheduleSnapshot(?object $offering): ?array
    {
        if (! $offering) {
            return null;
        }

        return [
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
    }

    private function roomSnapshot(?object $room): ?array
    {
        if (! $room) {
            return null;
        }

        return [
            'id' => (int) $room->id,
            'room_code' => (string) $room->room_code,
            'title' => $room->title ? (string) $room->title : null,
            'description' => $room->description ? (string) $room->description : null,
            'icon_key' => $room->icon_key ? (string) $room->icon_key : null,
            'building' => $room->building ? (string) $room->building : null,
            'capacity' => $room->capacity !== null ? (int) $room->capacity : null,
            'is_active' => (bool) $room->is_active,
        ];
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

    private function detectConflicts(array $payload, ?int $excludeId = null): array
    {
        $base = DB::table('class_offerings as o')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->where('o.period_id', $payload['period_id'])
            ->where('o.day_of_week', $payload['day_of_week'])
            ->where('o.start_time', '<', $payload['end_time'])
            ->where('o.end_time', '>', $payload['start_time']);

        if ($excludeId) {
            $base->where('o.id', '!=', $excludeId);
        }

        $errors = [];

        $roomConflict = (clone $base)
            ->where('o.room_id', $payload['room_id'])
            ->select('c.code', 'c.title', 'o.day_of_week', 'o.start_time', 'o.end_time', 'sr.room_code')
            ->first();
        if ($roomConflict) {
            $errors[] = sprintf(
                'Room conflict: %s is already used every %s at %s-%s (%s - %s).',
                $roomConflict->room_code,
                $roomConflict->day_of_week,
                $this->formatTimeLabel((string) $roomConflict->start_time),
                $this->formatTimeLabel((string) $roomConflict->end_time),
                $roomConflict->code,
                $roomConflict->title
            );
        }

        $teacherConflict = (clone $base)
            ->where('o.teacher_id', $payload['teacher_id'])
            ->select('c.code', 'c.title', 'o.day_of_week', 'o.start_time', 'o.end_time', 'st.full_name')
            ->first();
        if ($teacherConflict) {
            $errors[] = "Teacher conflict: {$teacherConflict->full_name} already has {$teacherConflict->code} - {$teacherConflict->title} ({$teacherConflict->day_of_week} {$teacherConflict->start_time}-{$teacherConflict->end_time}).";
        }

        $sectionConflict = (clone $base)
            ->where('o.section_id', $payload['section_id'])
            ->select('c.code', 'c.title', 'o.day_of_week', 'o.start_time', 'o.end_time', 'ss.section_code')
            ->first();
        if ($sectionConflict) {
            $errors[] = "Section conflict: {$sectionConflict->section_code} already has {$sectionConflict->code} - {$sectionConflict->title} ({$sectionConflict->day_of_week} {$sectionConflict->start_time}-{$sectionConflict->end_time}).";
        }

        return $errors;
    }

    public function masters(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $rooms = DB::table('schedule_rooms as sr')
            ->leftJoin('users as cu', 'cu.id', '=', 'sr.created_by_user_id')
            ->leftJoin('users as uu', 'uu.id', '=', 'sr.updated_by_user_id')
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
            'periods' => DB::table('enrollment_periods')->orderByDesc('id')->get(),
            'teachers' => DB::table('schedule_teachers')->where('is_active', 1)->orderBy('full_name')->get(),
            'rooms' => $rooms,
            'sections' => DB::table('schedule_sections')->where('is_active', 1)->orderBy('section_code')->get(),
            'courses' => DB::table('courses')->where('is_active', 1)->orderBy('code')->select('id', 'code', 'title', 'units', 'year_level', 'semester', 'program_key')->get(),
            'room_icon_presets' => self::ROOM_ICON_PRESETS,
        ]);
    }

    public function rooms(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
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

    public function storeRoom(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
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

        $snapshot = $this->roomSnapshot(DB::table('schedule_rooms')->where('id', $roomId)->first());
        $this->writeSchedulingActionLog([
            'entity_type' => 'room',
            'entity_id' => $roomId,
            'action' => 'create',
            'acted_by_user_id' => $user->id,
            'room_id' => $roomId,
            'after_snapshot' => $snapshot,
        ]);

        return response()->json([
            'message' => 'Room created successfully.',
            'item' => $snapshot,
        ], 201);
    }

    public function updateRoom(Request $request, int $roomId): JsonResponse
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
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

        $this->writeSchedulingActionLog([
            'entity_type' => 'room',
            'entity_id' => $roomId,
            'action' => 'update',
            'acted_by_user_id' => $user->id,
            'room_id' => $roomId,
            'before_snapshot' => $this->roomSnapshot($room),
            'after_snapshot' => $this->roomSnapshot(DB::table('schedule_rooms')->where('id', $roomId)->first()),
        ]);

        return response()->json(['message' => 'Room updated successfully.']);
    }

    public function destroyRoom(Request $request, int $roomId): JsonResponse
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
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
                'before_snapshot' => $this->scheduleSnapshot($offering),
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
            'before_snapshot' => $this->roomSnapshot($room),
            'note' => "Cascade offerings: {$impact['offering_count']}",
        ]);

        return response()->json([
            'message' => 'Room deleted successfully. Linked offerings were cascaded.',
            'impact' => $impact,
        ]);
    }

    public function roomAvailability(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
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
                $start = $this->formatTimeLabel((string) $entry->start_time);
                $end = $this->formatTimeLabel((string) $entry->end_time);
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

    public function offerings(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $periodId = $request->query('period_id');
        $search = trim((string) $request->query('search', ''));
        $sectionId = $request->query('section_id');
        $teacherId = $request->query('teacher_id');
        $roomId = $request->query('room_id');
        $yearLevel = $request->query('year_level');
        $semester = $request->query('semester');

        $enrollCounts = DB::table('enrollments')
            ->select('offering_id', DB::raw('COUNT(*) as enrolled_count'))
            ->whereNotNull('offering_id')
            ->whereIn('status', ['draft', 'unofficial', 'official'])
            ->groupBy('offering_id');

        $query = DB::table('class_offerings as o')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('enrollment_periods as p', 'p.id', '=', 'o.period_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->leftJoin('users as uu', 'uu.id', '=', 'o.updated_by_user_id')
            ->leftJoinSub($enrollCounts, 'ec', function ($join) {
                $join->on('ec.offering_id', '=', 'o.id');
            })
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
                'uu.name as updated_by_name',
                'p.name as period_name',
                'c.code as course_code',
                'c.title as course_title',
                'c.units',
                'c.year_level',
                'c.semester',
                'c.program_key',
                'ss.section_code',
                'st.full_name as teacher_name',
                'sr.room_code',
                DB::raw('COALESCE(ec.enrolled_count, 0) as enrolled_count')
            )
            ->orderByDesc('o.id');

        if ($periodId && $periodId !== 'all') {
            $query->where('o.period_id', (int) $periodId);
        }
        if ($sectionId && $sectionId !== 'all') {
            $query->where('o.section_id', (int) $sectionId);
        }
        if ($teacherId && $teacherId !== 'all') {
            $query->where('o.teacher_id', (int) $teacherId);
        }
        if ($roomId && $roomId !== 'all') {
            $query->where('o.room_id', (int) $roomId);
        }
        if ($yearLevel && $yearLevel !== 'all') {
            $query->where('c.year_level', (int) $yearLevel);
        }
        if ($semester && $semester !== 'all') {
            $query->where('c.semester', (int) $semester);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('c.code', 'like', "%{$search}%")
                    ->orWhere('c.title', 'like', "%{$search}%")
                    ->orWhere('ss.section_code', 'like', "%{$search}%")
                    ->orWhere('st.full_name', 'like', "%{$search}%")
                    ->orWhere('sr.room_code', 'like', "%{$search}%");
            });
        }

        $items = $query->get()->map(function ($row) {
            $row->slots_left = max(0, (int) $row->capacity - (int) $row->enrolled_count);
            return $row;
        })->values();

        return response()->json(['items' => $items]);
    }

    public function upsertOffering(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'offering_id' => ['nullable', 'integer', 'exists:class_offerings,id'],
            'period_id' => ['required', 'integer', 'exists:enrollment_periods,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'section_id' => ['required', 'integer', 'exists:schedule_sections,id'],
            'teacher_id' => ['required', 'integer', 'exists:schedule_teachers,id'],
            'room_id' => ['required', 'integer', 'exists:schedule_rooms,id'],
            'day_of_week' => ['required', 'in:Mon,Tue,Wed,Thu,Fri,Sat'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $conflicts = $this->detectConflicts($validated, $validated['offering_id'] ?? null);
        if (! empty($conflicts)) {
            return response()->json([
                'message' => 'Schedule conflict detected.',
                'errors' => ['conflict' => $conflicts],
            ], 422);
        }

        $data = [
            'period_id' => (int) $validated['period_id'],
            'course_id' => (int) $validated['course_id'],
            'section_id' => (int) $validated['section_id'],
            'teacher_id' => (int) $validated['teacher_id'],
            'room_id' => (int) $validated['room_id'],
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'schedule_text' => $this->formatScheduleText($validated['day_of_week'], $validated['start_time'], $validated['end_time']),
            'capacity' => (int) ($validated['capacity'] ?? 40),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
            'updated_by_user_id' => $user->id,
            'updated_at' => now(),
        ];

        if (! empty($validated['offering_id'])) {
            $offeringId = (int) $validated['offering_id'];
            $before = DB::table('class_offerings')->where('id', $offeringId)->first();
            DB::table('class_offerings')->where('id', $offeringId)->update($data);
            $after = DB::table('class_offerings')->where('id', $offeringId)->first();
            $this->writeSchedulingActionLog([
                'entity_type' => 'offering',
                'entity_id' => $offeringId,
                'action' => 'update',
                'acted_by_user_id' => $user->id,
                'offering_id' => $offeringId,
                'room_id' => (int) $validated['room_id'],
                'period_id' => (int) $validated['period_id'],
                'before_snapshot' => $this->scheduleSnapshot($before),
                'after_snapshot' => $this->scheduleSnapshot($after),
            ]);
            return response()->json(['message' => 'Class offering updated.']);
        }

        $data['created_by_user_id'] = $user->id;
        $data['created_at'] = now();
        $offeringId = (int) DB::table('class_offerings')->insertGetId($data);
        $created = DB::table('class_offerings')->where('id', $offeringId)->first();
        $this->writeSchedulingActionLog([
            'entity_type' => 'offering',
            'entity_id' => $offeringId,
            'action' => 'create',
            'acted_by_user_id' => $user->id,
            'offering_id' => $offeringId,
            'room_id' => (int) $validated['room_id'],
            'period_id' => (int) $validated['period_id'],
            'after_snapshot' => $this->scheduleSnapshot($created),
        ]);
        return response()->json(['message' => 'Class offering created.'], 201);
    }

    public function bulkUpsert(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.offering_id' => ['nullable', 'integer', 'exists:class_offerings,id'],
            'items.*.period_id' => ['required', 'integer', 'exists:enrollment_periods,id'],
            'items.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'items.*.section_id' => ['required', 'integer', 'exists:schedule_sections,id'],
            'items.*.teacher_id' => ['required', 'integer', 'exists:schedule_teachers,id'],
            'items.*.room_id' => ['required', 'integer', 'exists:schedule_rooms,id'],
            'items.*.day_of_week' => ['required', 'in:Mon,Tue,Wed,Thu,Fri,Sat'],
            'items.*.start_time' => ['required', 'date_format:H:i'],
            'items.*.end_time' => ['required', 'date_format:H:i'],
            'items.*.capacity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        foreach ($validated['items'] as $item) {
            if (($item['start_time'] ?? '') >= ($item['end_time'] ?? '')) {
                return response()->json([
                    'message' => 'Invalid schedule range.',
                    'errors' => ['time' => ['End time must be after start time.']],
                ], 422);
            }
            $request->merge($item);
            $response = $this->upsertOffering($request);
            if ($response->getStatusCode() >= 400) {
                return $response;
            }
        }

        return response()->json(['message' => 'Bulk schedule save completed.']);
    }
}
