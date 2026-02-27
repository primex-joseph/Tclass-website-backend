<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentEnrollmentController extends Controller
{
    private function ensureRole(int $userId, string $role): bool
    {
        return DB::table('portal_user_roles')
            ->where('user_id', $userId)
            ->where('role', $role)
            ->where('is_active', 1)
            ->exists();
    }

    private function activePeriodId(): ?int
    {
        $period = DB::table('enrollment_periods')->where('is_active', 1)->orderByDesc('id')->first();
        return $period?->id;
    }

    private function periodIdFromRequest(Request $request): ?int
    {
        $requested = $request->query('period_id') ?? $request->input('period_id');
        return $requested ? (int) $requested : $this->activePeriodId();
    }

    private function activeEnrollmentStatuses(): array
    {
        return ['draft', 'unofficial', 'official'];
    }

    private function findAvailableOffering(int $courseId, int $periodId): ?object
    {
        $enrolledCounts = DB::table('enrollments')
            ->select('offering_id', DB::raw('COUNT(*) as enrolled_count'))
            ->whereNotNull('offering_id')
            ->whereIn('status', $this->activeEnrollmentStatuses())
            ->groupBy('offering_id');

        return DB::table('class_offerings as o')
            ->leftJoinSub($enrolledCounts, 'ec', function ($join) {
                $join->on('ec.offering_id', '=', 'o.id');
            })
            ->where('o.is_active', 1)
            ->where('o.period_id', $periodId)
            ->where('o.course_id', $courseId)
            ->whereRaw('COALESCE(ec.enrolled_count, 0) < o.capacity')
            ->orderBy('o.section_id')
            ->orderBy('o.day_of_week')
            ->orderBy('o.start_time')
            ->select('o.*', DB::raw('COALESCE(ec.enrolled_count, 0) as enrolled_count'))
            ->first();
    }

    private function passedCourseIds(int $userId): Collection
    {
        return DB::table('student_course_results')
            ->where('user_id', $userId)
            ->where('status', 'passed')
            ->pluck('course_id');
    }

    private function normalizeProgramKey(?string $programName): ?string
    {
        $value = trim((string) $programName);
        if ($value === '') {
            return null;
        }

        $normalized = Str::upper(Str::slug($value, '_'));
        return $normalized !== '' ? $normalized : null;
    }

    private function studentProgramKey(int $userId): ?string
    {
        $programName = DB::table('admission_applications')
            ->where('created_user_id', $userId)
            ->where('status', 'approved')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->value('primary_course');

        return $this->normalizeProgramKey($programName);
    }

    private function nextCurriculumTerm(int $userId, ?string $programKey = null): array
    {
        $passed = $this->passedCourseIds($userId);
        $termsQuery = DB::table('courses')
            ->where('is_active', 1);

        if ($programKey) {
            $termsQuery->where('program_key', $programKey);
        }

        $terms = $termsQuery
            ->select('year_level', 'semester')
            ->distinct()
            ->orderBy('year_level')
            ->orderBy('semester')
            ->get();

        foreach ($terms as $term) {
            $termCoursesQuery = DB::table('courses')
                ->where('is_active', 1)
                ->where('year_level', $term->year_level)
                ->where('semester', $term->semester);

            if ($programKey) {
                $termCoursesQuery->where('program_key', $programKey);
            }

            $termCourseIds = $termCoursesQuery->pluck('id');

            $allPassed = $termCourseIds->every(fn ($courseId) => $passed->contains($courseId));
            if (! $allPassed) {
                return ['year_level' => $term->year_level, 'semester' => $term->semester];
            }
        }

        $latest = $terms->last();
        return [
            'year_level' => $latest?->year_level ?? 1,
            'semester' => $latest?->semester ?? 1,
        ];
    }

    public function periods(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'periods' => DB::table('enrollment_periods')->orderByDesc('id')->get(),
            'active_period_id' => $this->activePeriodId(),
        ]);
    }

    public function curriculumEvaluation(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $programKey = $this->studentProgramKey($user->id);

        $query = DB::table('courses as c')
            ->leftJoin('student_course_results as r', function ($join) use ($user) {
                $join->on('r.course_id', '=', 'c.id')->where('r.user_id', $user->id);
            })
            ->where('c.is_active', 1)
            ->select(
                'c.id',
                'c.code',
                'c.title',
                'c.units',
                'c.year_level',
                'c.semester',
                'r.grade',
                'r.status as result_status'
            );

        if ($programKey) {
            $query->where('c.program_key', $programKey);
        }

        $rows = $query
            ->orderBy('c.year_level')
            ->orderBy('c.semester')
            ->orderBy('c.code')
            ->get();

        $next = $this->nextCurriculumTerm($user->id, $programKey);

        return response()->json([
            'program_key' => $programKey,
            'next_term' => $next,
            'evaluation' => $rows,
        ]);
    }

    public function courses(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $programKey = $this->studentProgramKey($user->id);

        $query = DB::table('courses')
            ->where('is_active', 1)
            ->orderBy('year_level')
            ->orderBy('semester')
            ->orderBy('code');

        if ($programKey) {
            $query->where('program_key', $programKey);
        }

        $courses = $query->get();

        return response()->json(['courses' => $courses]);
    }

    public function enrollmentOfferings(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:enrollment_periods,id'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        $periodId = $validated['period_id'] ?? $this->activePeriodId();
        if (! $periodId) {
            return response()->json(['offerings' => []]);
        }

        $programKey = $this->studentProgramKey($user->id);

        $enrolledCounts = DB::table('enrollments')
            ->select('offering_id', DB::raw('COUNT(*) as enrolled_count'))
            ->whereNotNull('offering_id')
            ->whereIn('status', $this->activeEnrollmentStatuses())
            ->groupBy('offering_id');

        $query = DB::table('class_offerings as o')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->leftJoinSub($enrolledCounts, 'ec', function ($join) {
                $join->on('ec.offering_id', '=', 'o.id');
            })
            ->where('o.is_active', 1)
            ->where('o.period_id', $periodId)
            ->where('c.is_active', 1)
            ->whereRaw('COALESCE(ec.enrolled_count, 0) < o.capacity')
            ->select(
                'o.id as offering_id',
                'c.id as course_id',
                'c.code',
                'c.title',
                'c.units',
                'c.year_level',
                'c.semester',
                'ss.section_code as section',
                'st.full_name as instructor',
                'sr.room_code as room',
                'o.day_of_week',
                'o.start_time',
                'o.end_time',
                'o.schedule_text',
                'o.capacity',
                DB::raw('COALESCE(ec.enrolled_count, 0) as enrolled_count')
            )
            ->orderBy('c.code')
            ->orderBy('ss.section_code')
            ->orderBy('o.day_of_week')
            ->orderBy('o.start_time');

        if ($programKey) {
            $query->where('c.program_key', $programKey);
        }
        if (! empty($validated['year_level'])) {
            $query->where('c.year_level', (int) $validated['year_level']);
        }
        if (! empty($validated['semester'])) {
            $query->where('c.semester', (int) $validated['semester']);
        }

        $rows = $query->get()->map(function ($row) {
            $startTs = strtotime("1970-01-01 {$row->start_time}");
            $endTs = strtotime("1970-01-01 {$row->end_time}");
            $startLabel = $startTs ? date('h:i A', $startTs) : $row->start_time;
            $endLabel = $endTs ? date('h:i A', $endTs) : $row->end_time;
            $row->schedule = $row->schedule_text ?: "{$row->day_of_week} {$startLabel} - {$endLabel}";
            $row->slots_left = max(0, (int) $row->capacity - (int) $row->enrolled_count);
            return $row;
        })->values();

        return response()->json([
            'program_key' => $programKey,
            'period_id' => $periodId,
            'offerings' => $rows,
        ]);
    }

    public function preEnlisted(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $periodId = $this->periodIdFromRequest($request);

        $rows = DB::table('enrollments as e')
            ->join('courses as c', 'c.id', '=', 'e.course_id')
            ->leftJoin('class_offerings as o', 'o.id', '=', 'e.offering_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->where('e.user_id', $user->id)
            ->where('e.period_id', $periodId)
            ->whereIn('e.status', ['draft'])
            ->select(
                'e.id',
                'e.status',
                'e.remarks',
                'c.id as course_id',
                'c.code',
                'c.title',
                'c.units',
                'c.tf',
                'c.lec',
                'c.lab',
                DB::raw('COALESCE(o.schedule_text, c.schedule) as schedule'),
                DB::raw('COALESCE(ss.section_code, c.section) as section'),
                DB::raw('COALESCE(st.full_name, c.instructor) as instructor'),
                DB::raw('COALESCE(sr.room_code, c.room) as room')
            )
            ->orderBy('c.code')
            ->get();

        return response()->json(['pre_enlisted' => $rows]);
    }

    public function enrolledSubjects(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $periodId = $this->periodIdFromRequest($request);

        $rows = DB::table('enrollments as e')
            ->join('courses as c', 'c.id', '=', 'e.course_id')
            ->leftJoin('class_offerings as o', 'o.id', '=', 'e.offering_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'o.room_id')
            ->leftJoin('users as admin', 'admin.id', '=', 'e.decided_by')
            ->where('e.user_id', $user->id)
            ->where('e.period_id', $periodId)
            ->whereIn('e.status', ['unofficial', 'official'])
            ->select(
                'e.id',
                'e.status',
                'e.assessed_at',
                'e.decided_at',
                'c.id as course_id',
                'c.code',
                'c.title',
                'c.units',
                DB::raw('COALESCE(o.schedule_text, c.schedule) as schedule'),
                DB::raw('COALESCE(sr.room_code, c.room) as room'),
                DB::raw('COALESCE(st.full_name, c.instructor) as instructor'),
                DB::raw('COALESCE(ss.section_code, c.section) as section'),
                'admin.name as approved_by'
            )
            ->orderBy('c.code')
            ->get();

        $isOfficial = $rows->count() > 0 && $rows->every(fn ($row) => $row->status === 'official');

        return response()->json([
            'enrollment_status' => $rows->isEmpty() ? 'not_enrolled' : ($isOfficial ? 'official' : 'unofficial'),
            'official' => $isOfficial,
            'enrolled_subjects' => $rows,
            'total_units' => $rows->sum('units'),
        ]);
    }

    public function add(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'period_id' => ['nullable', 'integer', 'exists:enrollment_periods,id'],
            'offering_id' => ['nullable', 'integer', 'exists:class_offerings,id'],
        ]);

        $periodId = $validated['period_id'] ?? $this->activePeriodId();
        if (! $periodId) {
            return response()->json(['message' => 'No active period available.'], 422);
        }

        $offeringId = $validated['offering_id'] ?? null;

        if ($offeringId) {
            $selectedOffering = DB::table('class_offerings')
                ->where('id', $offeringId)
                ->where('period_id', $periodId)
                ->where('course_id', $validated['course_id'])
                ->where('is_active', 1)
                ->first();

            if (! $selectedOffering) {
                return response()->json(['message' => 'Selected section schedule is invalid for this course/period.'], 422);
            }

            $activeCount = DB::table('enrollments')
                ->where('offering_id', $offeringId)
                ->whereIn('status', $this->activeEnrollmentStatuses())
                ->count();

            if ($activeCount >= (int) $selectedOffering->capacity) {
                return response()->json(['message' => 'Selected section is already full.'], 422);
            }
        } else {
            $offering = $this->findAvailableOffering((int) $validated['course_id'], (int) $periodId);
            if (! $offering) {
                return response()->json(['message' => 'No available section schedule for this subject in the selected period.'], 422);
            }
            $offeringId = (int) $offering->id;
        }

        $existing = DB::table('enrollments')
            ->where('user_id', $user->id)
            ->where('course_id', $validated['course_id'])
            ->where('period_id', $periodId)
            ->first();

        if ($existing) {
            if (in_array($existing->status, ['unofficial', 'official'], true)) {
                return response()->json(['message' => 'Subject is already in enrolled subjects for this period.'], 422);
            }
            if ($existing->status === 'draft') {
                return response()->json(['message' => 'Subject already in pre-enlisted list.'], 422);
            }

            DB::table('enrollments')->where('id', $existing->id)->update([
                'status' => 'draft',
                'offering_id' => $offeringId,
                'remarks' => null,
                'requested_at' => null,
                'assessed_at' => null,
                'decided_at' => null,
                'decided_by' => null,
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Subject added to pre-enlisted list.']);
        }

        DB::table('enrollments')->insert([
            'user_id' => $user->id,
            'course_id' => $validated['course_id'],
            'period_id' => $periodId,
            'offering_id' => $offeringId,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Subject added to pre-enlisted list.'], 201);
    }

    public function auto(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:enrollment_periods,id'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        $periodId = $validated['period_id'] ?? $this->periodIdFromRequest($request);
        if (! $periodId) {
            return response()->json(['message' => 'No active period available.'], 422);
        }

        $programKey = $this->studentProgramKey($user->id);
        $next = (! empty($validated['year_level']) && ! empty($validated['semester']))
            ? ['year_level' => (int) $validated['year_level'], 'semester' => (int) $validated['semester']]
            : $this->nextCurriculumTerm($user->id, $programKey);
        $passed = $this->passedCourseIds($user->id);

        $termCoursesQuery = DB::table('courses')
            ->where('is_active', 1)
            ->where('year_level', $next['year_level'])
            ->where('semester', $next['semester'])
            ->orderBy('code');

        if ($programKey) {
            $termCoursesQuery->where('program_key', $programKey);
        }

        $termCourses = $termCoursesQuery->get();

        $addedCount = 0;
        $skippedNoSection = [];
        foreach ($termCourses as $course) {
            if ($course->prerequisite_course_id && ! $passed->contains($course->prerequisite_course_id)) {
                continue;
            }

            $offering = $this->findAvailableOffering((int) $course->id, (int) $periodId);
            if (! $offering) {
                $skippedNoSection[] = $course->code;
                continue;
            }

            $exists = DB::table('enrollments')
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('period_id', $periodId)
                ->first();

            if (! $exists) {
                DB::table('enrollments')->insert([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'period_id' => $periodId,
                    'offering_id' => $offering->id,
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $addedCount++;
            } elseif ($exists->status === 'draft' && ! $exists->offering_id) {
                DB::table('enrollments')->where('id', $exists->id)->update([
                    'offering_id' => $offering->id,
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json([
            'message' => $addedCount > 0
                ? "Auto pre-enlist added {$addedCount} subjects."
                : 'No subjects added by auto pre-enlist (check curriculum/prerequisites).',
            'added_count' => $addedCount,
            'skipped_no_section' => $skippedNoSection,
            'program_key' => $programKey,
            'next_term' => $next,
        ]);
    }

    public function remove(Request $request, int $enrollmentId)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $row = DB::table('enrollments')->where('id', $enrollmentId)->where('user_id', $user->id)->first();
        if (! $row) {
            return response()->json(['message' => 'Enrollment row not found.'], 404);
        }

        if ($row->status !== 'draft') {
            return response()->json(['message' => 'Only draft pre-enlisted subjects can be deleted.'], 422);
        }

        DB::table('enrollments')->where('id', $row->id)->delete();

        return response()->json(['message' => 'Subject removed from pre-enlisted list.']);
    }

    public function clearDraft(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $periodId = $this->periodIdFromRequest($request);

        DB::table('enrollments')
            ->where('user_id', $user->id)
            ->where('period_id', $periodId)
            ->where('status', 'draft')
            ->delete();

        return response()->json(['message' => 'Draft pre-enlisted subjects cleared.']);
    }

    public function assess(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'student')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $periodId = $this->periodIdFromRequest($request);

        $draftRows = DB::table('enrollments')
            ->where('user_id', $user->id)
            ->where('period_id', $periodId)
            ->where('status', 'draft')
            ->pluck('id');

        if ($draftRows->isEmpty()) {
            return response()->json(['message' => 'No draft subjects to assess.'], 422);
        }

        DB::table('enrollments')
            ->whereIn('id', $draftRows)
            ->update([
                'status' => 'unofficial',
                'assessed_at' => now(),
                'requested_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Subjects moved to Enrolled Subjects as Unofficially Enrolled.']);
    }
}
