<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminClassSchedulingController extends Controller
{
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
            $errors[] = "Room conflict: {$roomConflict->room_code} already has {$roomConflict->code} - {$roomConflict->title} ({$roomConflict->day_of_week} {$roomConflict->start_time}-{$roomConflict->end_time}).";
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

        return response()->json([
            'periods' => DB::table('enrollment_periods')->orderByDesc('id')->get(),
            'teachers' => DB::table('schedule_teachers')->where('is_active', 1)->orderBy('full_name')->get(),
            'rooms' => DB::table('schedule_rooms')->where('is_active', 1)->orderBy('room_code')->get(),
            'sections' => DB::table('schedule_sections')->where('is_active', 1)->orderBy('section_code')->get(),
            'courses' => DB::table('courses')->where('is_active', 1)->orderBy('code')->select('id', 'code', 'title', 'units', 'year_level', 'semester', 'program_key')->get(),
        ]);
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
            'updated_at' => now(),
        ];

        if (! empty($validated['offering_id'])) {
            DB::table('class_offerings')->where('id', (int) $validated['offering_id'])->update($data);
            return response()->json(['message' => 'Class offering updated.']);
        }

        $data['created_at'] = now();
        DB::table('class_offerings')->insert($data);
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

