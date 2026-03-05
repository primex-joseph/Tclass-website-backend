<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\EnrollmentPeriodRollover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminEnrollmentController extends Controller
{
    private function ensureRole(int $userId, string $role): bool
    {
        return DB::table('portal_user_roles')
            ->where('user_id', $userId)
            ->where('role', $role)
            ->where('is_active', 1)
            ->exists();
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $status = $request->query('status');
        $periodId = $request->query('period_id');

        $query = DB::table('enrollments as e')
            ->join('users as s', 's.id', '=', 'e.user_id')
            ->join('courses as c', 'c.id', '=', 'e.course_id')
            ->leftJoin('class_offerings as o', 'o.id', '=', 'e.offering_id')
            ->leftJoin('schedule_sections as ss_offer', 'ss_offer.id', '=', 'o.section_id')
            ->leftJoin('schedule_teachers as st_offer', 'st_offer.id', '=', 'o.teacher_id')
            ->leftJoin('schedule_rooms as sr_offer', 'sr_offer.id', '=', 'o.room_id')
            ->leftJoin('class_schedule_items as csi', 'csi.enrollment_id', '=', 'e.id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'csi.section_id')
            ->leftJoin('schedule_teachers as st', 'st.id', '=', 'csi.teacher_id')
            ->leftJoin('schedule_rooms as sr', 'sr.id', '=', 'csi.room_id')
            ->leftJoin('users as admin', 'admin.id', '=', 'e.decided_by')
            ->leftJoin('enrollment_periods as p', 'p.id', '=', 'e.period_id')
            ->select(
                'e.id', 'e.status', 'e.remarks', 'e.requested_at', 'e.assessed_at', 'e.decided_at',
                's.id as student_id', 's.name as student_name', 's.email as student_email',
                'c.id as course_id', 'c.code as course_code', 'c.title as course_title', 'c.units',
                DB::raw('COALESCE(o.schedule_text, csi.schedule_text, c.schedule) as schedule'),
                DB::raw('COALESCE(ss_offer.section_code, ss.section_code, c.section) as section'),
                DB::raw('COALESCE(st_offer.full_name, st.full_name, c.instructor) as instructor'),
                DB::raw('COALESCE(sr_offer.room_code, sr.room_code, c.room) as room'),
                'p.id as period_id', 'p.name as period_name',
                'admin.name as decided_by_name'
            )
            ->orderByDesc('e.id');

        if ($status) {
            $query->where('e.status', $status);
        }

        if ($periodId) {
            $query->where('e.period_id', $periodId);
        }

        return response()->json([
            'periods' => DB::table('enrollment_periods')->orderByDesc('id')->get(),
            'enrollments' => $query->get(),
        ]);
    }

    public function updateStatus(Request $request, int $enrollmentId)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:official,rejected'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $enrollment = DB::table('enrollments')->where('id', $enrollmentId)->first();
        if (! $enrollment) {
            return response()->json(['message' => 'Enrollment not found.'], 404);
        }

        if (! in_array($enrollment->status, ['unofficial', 'draft'], true)) {
            return response()->json(['message' => 'Only draft/unofficial requests can be updated.'], 422);
        }

        DB::table('enrollments')->where('id', $enrollmentId)->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Enrollment updated successfully.']);
    }

    public function activatePeriod(Request $request, int $periodId)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $period = DB::table('enrollment_periods')->where('id', $periodId)->first();
        if (! $period) {
            return response()->json(['message' => 'Period not found.'], 404);
        }

        DB::table('enrollment_periods')->update(['is_active' => 0, 'updated_at' => now()]);
        DB::table('enrollment_periods')->where('id', $periodId)->update(['is_active' => 1, 'updated_at' => now()]);

        return response()->json(['message' => 'Active enrollment period updated.']);
    }

    public function rolloverPeriod(Request $request)
    {
        $user = $request->user();
        if (! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $activate = (bool) $request->boolean('activate', false);
            $result = EnrollmentPeriodRollover::rolloverToNextPeriod(null, $activate);

            return response()->json([
                'message' => $activate
                    ? 'Enrollment period rolled over and activated successfully.'
                    : 'Next enrollment period prepared. Active term unchanged.',
                'from' => $result['from'],
                'to' => $result['to'],
                'activated' => $result['activated'] ?? false,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to roll over enrollment period.'], 500);
        }
    }
}
