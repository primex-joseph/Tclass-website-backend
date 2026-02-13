<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    private function passedCourseIds(int $userId): Collection
    {
        return DB::table('student_course_results')
            ->where('user_id', $userId)
            ->where('status', 'passed')
            ->pluck('course_id');
    }

    private function nextCurriculumTerm(int $userId): array
    {
        $passed = $this->passedCourseIds($userId);
        $terms = DB::table('courses')
            ->where('is_active', 1)
            ->select('year_level', 'semester')
            ->distinct()
            ->orderBy('year_level')
            ->orderBy('semester')
            ->get();

        foreach ($terms as $term) {
            $termCourseIds = DB::table('courses')
                ->where('is_active', 1)
                ->where('year_level', $term->year_level)
                ->where('semester', $term->semester)
                ->pluck('id');

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

        $rows = DB::table('courses as c')
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
            )
            ->orderBy('c.year_level')
            ->orderBy('c.semester')
            ->orderBy('c.code')
            ->get();

        $next = $this->nextCurriculumTerm($user->id);

        return response()->json([
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

        $courses = DB::table('courses')
            ->where('is_active', 1)
            ->orderBy('year_level')
            ->orderBy('semester')
            ->orderBy('code')
            ->get();

        return response()->json(['courses' => $courses]);
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
            ->where('e.user_id', $user->id)
            ->where('e.period_id', $periodId)
            ->whereIn('e.status', ['draft'])
            ->select('e.id', 'e.status', 'e.remarks', 'c.id as course_id', 'c.code', 'c.title', 'c.units', 'c.tf', 'c.lec', 'c.lab', 'c.schedule', 'c.section')
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
            ->leftJoin('users as admin', 'admin.id', '=', 'e.decided_by')
            ->where('e.user_id', $user->id)
            ->where('e.period_id', $periodId)
            ->whereIn('e.status', ['unofficial', 'official'])
            ->select('e.id', 'e.status', 'e.assessed_at', 'e.decided_at', 'c.id as course_id', 'c.code', 'c.title', 'c.units', 'c.schedule', 'c.room', 'c.instructor', 'c.section', 'admin.name as approved_by')
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
        ]);

        $periodId = $validated['period_id'] ?? $this->activePeriodId();
        if (! $periodId) {
            return response()->json(['message' => 'No active period available.'], 422);
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

        $periodId = $this->periodIdFromRequest($request);
        if (! $periodId) {
            return response()->json(['message' => 'No active period available.'], 422);
        }

        $next = $this->nextCurriculumTerm($user->id);
        $passed = $this->passedCourseIds($user->id);

        $termCourses = DB::table('courses')
            ->where('is_active', 1)
            ->where('year_level', $next['year_level'])
            ->where('semester', $next['semester'])
            ->orderBy('code')
            ->get();

        $addedCount = 0;
        foreach ($termCourses as $course) {
            if ($course->prerequisite_course_id && ! $passed->contains($course->prerequisite_course_id)) {
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
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $addedCount++;
            }
        }

        return response()->json([
            'message' => $addedCount > 0
                ? "Auto pre-enlist added {$addedCount} subjects."
                : 'No subjects added by auto pre-enlist (check curriculum/prerequisites).',
            'added_count' => $addedCount,
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
