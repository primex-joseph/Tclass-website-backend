<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdmissionApprovedMail;
use App\Mail\AdmissionRejectedMail;
use App\Mail\EntranceExamScheduleMail;
use App\Mail\PortalUserCredentialsMail;
use App\Models\AdmissionApplication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdmissionController extends Controller
{
    private function canonicalProgramName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        $normalized = Str::of($trimmed)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value();
        $aliases = [
            'bs information technology' => 'Bachelor of Science in Information Technology',
            'bsit' => 'Bachelor of Science in Information Technology',
            'b s information technology' => 'Bachelor of Science in Information Technology',
        ];

        return $aliases[$normalized] ?? $trimmed;
    }

    private function canonicalProgramKey(string $name): string
    {
        $canonical = $this->canonicalProgramName($name);
        return strtoupper(Str::slug($canonical, '_'));
    }

    private function extractDepartmentKey(?string $courseCode): string
    {
        $value = strtoupper(trim((string) $courseCode));
        if ($value === '') {
            return 'GENERAL';
        }

        $prefix = preg_replace('/[^A-Z].*$/', '', $value);
        return $prefix !== '' ? $prefix : $value;
    }

    private function currentRole(Request $request): ?string
    {
        return DB::table('portal_user_roles')
            ->where('user_id', $request->user()->id)
            ->where('is_active', 1)
            ->value('role');
    }

    private function assertAdmin(Request $request): ?JsonResponse
    {
        if ($this->currentRole($request) !== 'admin') {
            return response()->json(['message' => 'Forbidden. Admin role required.'], 403);
        }

        return null;
    }

    private function assertStudent(Request $request): ?JsonResponse
    {
        if ($this->currentRole($request) !== 'student') {
            return response()->json(['message' => 'Forbidden. Student role required.'], 403);
        }

        return null;
    }

    private function latestStudentApplication(int $userId): ?AdmissionApplication
    {
        return AdmissionApplication::query()
            ->where('created_user_id', $userId)
            ->orderByRaw('CASE WHEN approved_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->first();
    }

    private function splitFullNameForAdminList(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $parts = array_values(array_filter($parts, fn ($value) => trim((string) $value) !== ''));

        if (count($parts) === 0) {
            return ['first_name' => '', 'last_name' => ''];
        }
        if (count($parts) === 1) {
            return ['first_name' => (string) $parts[0], 'last_name' => ''];
        }

        $lastName = (string) array_pop($parts);
        $firstName = trim(implode(' ', $parts));

        return ['first_name' => $firstName, 'last_name' => $lastName];
    }

    private function toYearLevelLabel(int $yearLevel): string
    {
        if ($yearLevel <= 0) {
            return '';
        }
        if ($yearLevel === 1) return '1st Year';
        if ($yearLevel === 2) return '2nd Year';
        if ($yearLevel === 3) return '3rd Year';
        if ($yearLevel === 4) return '4th Year';
        return $yearLevel . 'th Year';
    }

    private function normalizeStudentProgramKey(?string $programName): ?string
    {
        $value = trim((string) $programName);
        if ($value === '') {
            return null;
        }

        $normalized = Str::upper(Str::slug($value, '_'));
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'BACHELOR_OF_SCIENCE_IN_')) {
            $normalized = 'BS_' . substr($normalized, strlen('BACHELOR_OF_SCIENCE_IN_'));
        }

        if ($normalized === 'BSIT' || str_contains($normalized, 'INFORMATION_TECHNOLOGY')) {
            return 'BS_INFORMATION_TECHNOLOGY';
        }

        return $normalized;
    }

    private function passedCourseIdsForStudent(int $userId)
    {
        return DB::table('student_course_results')
            ->where('user_id', $userId)
            ->where('status', 'passed')
            ->pluck('course_id');
    }

    private function nextCurriculumTermForStudent(int $userId, ?string $programKey = null): array
    {
        $passed = $this->passedCourseIdsForStudent($userId);

        $termsQuery = DB::table('courses')->where('is_active', 1);
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
                return ['year_level' => (int) $term->year_level, 'semester' => (int) $term->semester];
            }
        }

        $latest = $terms->last();
        return [
            'year_level' => (int) ($latest?->year_level ?? 1),
            'semester' => (int) ($latest?->semester ?? 1),
        ];
    }
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:12', 'max:90'],
            'gender' => ['nullable', 'string', 'max:20'],
            'primary_course' => ['nullable', 'string', 'max:255'],
            'secondary_course' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'application_type' => ['nullable', 'in:admission,vocational'],
            'valid_id_type' => ['nullable', 'string', 'max:120'],
            'facebook_account' => ['nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:50'],
            'enrollment_purposes' => ['nullable', 'array'],
            'enrollment_purposes.*' => ['nullable', 'string', 'max:120'],
            'enrollment_purpose_others' => ['nullable', 'string', 'max:255'],
            'form_data' => ['nullable'],
            'id_picture' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'one_by_one_picture' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'right_thumbmark' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'birth_certificate' => ['nullable', 'file', 'mimes:pdf', 'max:4096'],
            'valid_id_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'valid_id_back_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        $rawFormData = $validated['form_data'] ?? [];
        if (is_string($rawFormData)) {
            $decoded = json_decode($rawFormData, true);
            $rawFormData = is_array($decoded) ? $decoded : [];
        }
        $formData = is_array($rawFormData) ? $rawFormData : [];

        $nameFromForm = trim(implode(' ', array_filter([
            data_get($formData, 'firstName'),
            data_get($formData, 'middleName'),
            data_get($formData, 'lastName'),
            data_get($formData, 'extensionName'),
        ])));
        $fullName = trim((string) ($validated['full_name'] ?? $nameFromForm));
        if ($fullName === '') {
            return response()->json(['message' => 'Full name is required.'], 422);
        }

        $age = $validated['age'] ?? data_get($formData, 'age');
        $applicationType = $validated['application_type'] ?? 'admission';
        $minAge = $applicationType === 'vocational' ? 18 : 12;
        if (! is_numeric($age) || (int) $age < $minAge || (int) $age > 90) {
            return response()->json(['message' => "Age must be between {$minAge} and 90."], 422);
        }

        $gender = trim((string) ($validated['gender'] ?? data_get($formData, 'sex')));
        if ($gender === '') {
            return response()->json(['message' => 'Gender is required.'], 422);
        }

        $primaryCourse = trim((string) ($validated['primary_course'] ?? data_get($formData, 'courseQualificationName')));
        if ($primaryCourse === '') {
            return response()->json(['message' => 'Primary course is required.'], 422);
        }

        $secondaryCourse = $validated['secondary_course'] ?? data_get($formData, 'scholarshipType');
        $facebookAccount = $validated['facebook_account'] ?? data_get($formData, 'facebookAccount');
        $contactNo = $validated['contact_no'] ?? data_get($formData, 'contactNo');
        $validIdType = $validated['valid_id_type'] ?? data_get($formData, 'validIdType');
        $purposesFromPayload = $validated['enrollment_purposes'] ?? data_get($formData, 'enrollmentPurposes', []);
        if (is_string($purposesFromPayload)) {
            $decodedPurposes = json_decode($purposesFromPayload, true);
            $purposesFromPayload = is_array($decodedPurposes) ? $decodedPurposes : [];
        }
        $enrollmentPurposes = collect(is_array($purposesFromPayload) ? $purposesFromPayload : [])
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn ($item) => trim($item))
            ->unique()
            ->values()
            ->all();
        $enrollmentPurposeOthers = trim((string) ($validated['enrollment_purpose_others'] ?? data_get($formData, 'enrollmentPurposeOthers', '')));
        $normalizedEmail = Str::lower($validated['email']);

        if (! $request->hasFile('valid_id_image')) {
            return response()->json(['message' => 'Valid ID front image upload is required.'], 422);
        }
        if (! $request->hasFile('valid_id_back_image')) {
            return response()->json(['message' => 'Valid ID back image upload is required.'], 422);
        }

        if ($applicationType === 'vocational') {
            if (! $request->hasFile('birth_certificate')) {
                return response()->json(['message' => 'Birth certificate upload is required.'], 422);
            }
            if (trim((string) $validIdType) === '') {
                return response()->json(['message' => 'Valid ID type is required.'], 422);
            }
            if (count($enrollmentPurposes) === 0) {
                return response()->json(['message' => 'Please select at least one purpose/intention for enrolling.'], 422);
            }
        }

        if (in_array('Others', $enrollmentPurposes, true) && $enrollmentPurposeOthers === '') {
            return response()->json(['message' => 'Please specify the purpose under Others.'], 422);
        }
        if (! in_array('Others', $enrollmentPurposes, true)) {
            $enrollmentPurposeOthers = null;
        }

        $existsPending = AdmissionApplication::query()
            ->where('email', $normalizedEmail)
            ->where('status', 'pending')
            ->exists();

        if ($existsPending) {
            return response()->json([
                'message' => 'An admission application for this email is already pending.',
            ], 422);
        }

        $application = AdmissionApplication::query()->create([
            'full_name' => $fullName,
            'age' => (int) $age,
            'gender' => $gender,
            'primary_course' => $primaryCourse,
            'secondary_course' => $secondaryCourse,
            'email' => $normalizedEmail,
            'application_type' => $applicationType,
            'valid_id_type' => $validIdType,
            'facebook_account' => $facebookAccount,
            'contact_no' => $contactNo,
            'enrollment_purposes' => $enrollmentPurposes,
            'enrollment_purpose_others' => $enrollmentPurposeOthers,
            'form_data' => $formData,
            'id_picture_path' => $request->hasFile('id_picture')
                ? $request->file('id_picture')->store('admissions/id-pictures', 'public')
                : null,
            'one_by_one_picture_path' => $request->hasFile('one_by_one_picture')
                ? $request->file('one_by_one_picture')->store('admissions/one-by-one', 'public')
                : null,
            'right_thumbmark_path' => $request->hasFile('right_thumbmark')
                ? $request->file('right_thumbmark')->store('admissions/thumbmarks', 'public')
                : null,
            'birth_certificate_path' => $request->hasFile('birth_certificate')
                ? $request->file('birth_certificate')->store('admissions/birth-certificates', 'public')
                : null,
            'valid_id_path' => $request->hasFile('valid_id_image')
                ? $request->file('valid_id_image')->store('admissions/valid-ids', 'public')
                : null,
            'valid_id_back_path' => $request->hasFile('valid_id_back_image')
                ? $request->file('valid_id_back_image')->store('admissions/valid-ids-back', 'public')
                : null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Admission submitted successfully.',
            'application' => $application,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $applications = AdmissionApplication::query()
            ->latest('id')
            ->get();

        return response()->json([
            'applications' => $applications,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $role = strtolower((string) $request->query('role', 'student'));
        $allowedRoles = ['student', 'faculty', 'admin'];
        if (! in_array($role, $allowedRoles, true)) {
            return response()->json(['message' => 'Invalid role filter.'], 422);
        }

        $rows = DB::table('users')
            ->join('portal_user_roles', function ($join) use ($role) {
                $join->on('portal_user_roles.user_id', '=', 'users.id')
                    ->where('portal_user_roles.role', '=', $role)
                    ->where('portal_user_roles.is_active', '=', 1);
            })
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.student_number',
                'users.created_at',
                DB::raw("'" . $role . "' as role"),
                DB::raw("'active' as status"),
            ])
            ->orderByDesc('users.id')
            ->get();

        return response()->json([
            'users' => $rows,
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $latestApprovedApplications = DB::table('admission_applications as aa')
            ->joinSub(
                DB::table('admission_applications')
                    ->selectRaw('MAX(id) as id')
                    ->where('status', 'approved')
                    ->whereNotNull('created_user_id')
                    ->groupBy('created_user_id'),
                'latest_approved',
                fn ($join) => $join->on('latest_approved.id', '=', 'aa.id')
            )
            ->select('aa.created_user_id', 'aa.primary_course', 'aa.gender')
            ->get()
            ->keyBy('created_user_id');

        $rows = DB::table('users')
            ->join('portal_user_roles', function ($join) {
                $join->on('portal_user_roles.user_id', '=', 'users.id')
                    ->where('portal_user_roles.role', '=', 'student')
                    ->where('portal_user_roles.is_active', '=', 1);
            })
            ->select('users.id', 'users.name', 'users.email', 'users.student_number')
            ->orderByDesc('users.id')
            ->get()
            ->map(function ($user) use ($latestApprovedApplications) {
                $application = $latestApprovedApplications->get($user->id);
                $programName = trim((string) ($application->primary_course ?? ''));
                $gender = trim((string) ($application->gender ?? ''));
                $programKey = $this->normalizeStudentProgramKey($programName !== '' ? $programName : null);
                $nextTerm = $this->nextCurriculumTermForStudent((int) $user->id, $programKey);
                $yearLevelNumber = (int) ($nextTerm['year_level'] ?? 0);
                $nameParts = $this->splitFullNameForAdminList((string) $user->name);

                return [
                    'id' => (int) $user->id,
                    'first_name' => $nameParts['first_name'] !== '' ? $nameParts['first_name'] : (string) $user->name,
                    'last_name' => $nameParts['last_name'],
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'student_number' => (string) ($user->student_number ?? ''),
                    'course' => $programName !== '' ? $programName : null,
                    'gender' => $gender !== '' ? $gender : null,
                    'year_level_number' => $yearLevelNumber > 0 ? $yearLevelNumber : null,
                    'year_level' => $yearLevelNumber > 0 ? $this->toYearLevelLabel($yearLevelNumber) : null,
                ];
            })
            ->values();

        return response()->json([
            'students' => $rows,
        ]);
    }
    public function createPortalUser(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', 'in:student,faculty,admin'],
            'password' => ['nullable', 'string', 'min:8', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = strtolower((string) ($validated['role'] ?? 'admin'));
        $normalizedEmail = Str::lower((string) $validated['email']);
        $name = trim((string) $validated['name']);
        $isActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        if (User::query()->where('email', $normalizedEmail)->exists()) {
            return response()->json([
                'message' => 'A user with this email already exists.',
            ], 422);
        }

        $generatedPassword = null;
        $rawPassword = isset($validated['password']) && trim((string) $validated['password']) !== ''
            ? (string) $validated['password']
            : null;

        if ($rawPassword === null) {
            $generatedPassword = $this->generateTemporaryPassword();
            $rawPassword = $generatedPassword;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $normalizedEmail,
            'password' => Hash::make($rawPassword),
            'student_number' => $role === 'student' ? $this->generateStudentNumber() : null,
            'must_change_password' => true,
        ]);

        DB::table('portal_user_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role' => $role],
            ['is_active' => $isActive ? 1 : 0, 'created_at' => now(), 'updated_at' => now()]
        );

        $mailWarning = null;
        try {
            Mail::to($normalizedEmail)->send(
                new PortalUserCredentialsMail(
                    fullName: $name,
                    email: $normalizedEmail,
                    role: $role,
                    password: $rawPassword
                )
            );
        } catch (\Throwable $e) {
            $mailWarning = 'Account created, but credentials email failed to send. Check SMTP configuration.';
        }

        return response()->json([
            'message' => ucfirst($role) . ' account created successfully.',
            'warning' => $mailWarning,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'status' => $isActive ? 'active' : 'inactive',
                'created_at' => $user->created_at,
            ],
            'credentials_preview' => [
                'temporary_password' => $generatedPassword,
            ],
        ], 201);
    }

    public function dashboardStats(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $students = DB::table('portal_user_roles')
            ->where('role', 'student')
            ->where('is_active', 1)
            ->count();

        $faculty = DB::table('portal_user_roles')
            ->where('role', 'faculty')
            ->where('is_active', 1)
            ->count();

        $classes = DB::table('courses')
            ->where('is_active', 1)
            ->count();

        $departmentCodes = DB::table('courses')
            ->pluck('code')
            ->map(function ($code) {
                $value = strtoupper((string) $code);
                $prefix = preg_replace('/[^A-Z].*$/', '', $value);
                return $prefix !== '' ? $prefix : $value;
            })
            ->filter()
            ->unique()
            ->count();

        return response()->json([
            'students' => $students,
            'faculty' => $faculty,
            'classes' => $classes,
            'departments' => $departmentCodes,
        ]);
    }

    public function departmentOverview(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        // Program offerings (e.g., BSIT) come from curriculum versions.
        $programRows = DB::table('curriculum_versions')
            ->select('program_key', 'program_name', 'is_active', 'id')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        $programMap = [];
        foreach ($programRows as $row) {
            $canonicalName = $this->canonicalProgramName((string) $row->program_name);
            $key = $this->canonicalProgramKey($canonicalName);
            if ($key === '') {
                continue;
            }
            if (! isset($programMap[$key])) {
                $programMap[$key] = [
                    'id' => count($programMap) + 1,
                    'name' => $canonicalName,
                    'head' => '',
                    'faculty' => 0,
                    'students' => 0,
                    'classes' => 0,
                ];
            }
        }

        // Also include any admitted program names that might not yet be in curriculum_versions.
        $admissionPrograms = DB::table('admission_applications')
            ->where('application_type', 'admission')
            ->whereNotNull('primary_course')
            ->select('primary_course')
            ->distinct()
            ->pluck('primary_course');

        foreach ($admissionPrograms as $programName) {
            $name = trim((string) $programName);
            if ($name === '') {
                continue;
            }
            $canonicalName = $this->canonicalProgramName($name);
            $key = $this->canonicalProgramKey($canonicalName);
            if ($key === '') {
                continue;
            }
            if (! isset($programMap[$key])) {
                $programMap[$key] = [
                    'id' => count($programMap) + 1,
                    'name' => $canonicalName,
                    'head' => '',
                    'faculty' => 0,
                    'students' => 0,
                    'classes' => 0,
                ];
            }
        }

        // Student population per program: approved admissions linked to created student users.
        $population = DB::table('admission_applications')
            ->where('application_type', 'admission')
            ->where('status', 'approved')
            ->whereNotNull('created_user_id')
            ->select('primary_course', DB::raw('COUNT(DISTINCT created_user_id) as total_students'))
            ->groupBy('primary_course')
            ->get();

        foreach ($population as $row) {
            $name = trim((string) $row->primary_course);
            if ($name === '') {
                continue;
            }
            $key = $this->canonicalProgramKey($name);
            if (isset($programMap[$key])) {
                $programMap[$key]['students'] = (int) $row->total_students;
            }
        }

        $rows = array_values($programMap);
        usort($rows, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return response()->json([
            'departments' => $rows,
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $application = AdmissionApplication::query()->find($id);
        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        if ($application->status !== 'pending') {
            return response()->json(['message' => 'Only pending applications can be approved.'], 422);
        }

        $hasAttendanceColumn = Schema::hasColumn('admission_applications', 'exam_attendance_status');
        $attendanceStatus = $hasAttendanceColumn
            ? (string) ($application->exam_attendance_status ?? 'not_attended')
            : (($application->exam_status ?? 'not_attended') === 'not_attended' ? 'not_attended' : 'attended');
        $examResult = (string) ($application->exam_status ?? 'not_attended');

        if ($attendanceStatus !== 'attended' || $examResult !== 'passed') {
            return response()->json([
                'message' => 'Applicant can only be approved when exam attendance is Attended and exam result is Passed.',
            ], 422);
        }

        if (User::query()->where('email', $application->email)->exists()) {
            return response()->json([
                'message' => 'A user with this email already exists.',
            ], 422);
        }

        $studentNumber = $this->generateStudentNumber();
        $temporaryPassword = $this->generateTemporaryPassword();

        $user = User::query()->create([
            'name' => $application->full_name,
            'email' => $application->email,
            'student_number' => $studentNumber,
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        DB::table('portal_user_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role' => 'student'],
            ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        $application->update([
            'status' => 'approved',
            'approved_at' => now(),
            'processed_by' => $request->user()->id,
            'created_user_id' => $user->id,
        ]);

        $mailWarning = null;
        try {
            Mail::to($application->email)->send(
                new AdmissionApprovedMail(
                    fullName: $application->full_name,
                    studentNumber: $studentNumber,
                    temporaryPassword: $temporaryPassword
                )
            );
        } catch (\Throwable $e) {
            $mailWarning = 'Account created, but email sending failed. Check SMTP configuration.';
        }

        return response()->json([
            'message' => 'Admission approved and credentials sent via email.',
            'warning' => $mailWarning,
            'credentials_preview' => [
                'student_number' => $studentNumber,
                'temporary_password' => $temporaryPassword,
            ],
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $application = AdmissionApplication::query()->find($id);
        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        if ($application->status !== 'pending') {
            return response()->json(['message' => 'Only pending applications can be rejected.'], 422);
        }

        $application->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'processed_by' => $request->user()->id,
            'remarks' => $validated['remarks'],
        ]);

        try {
            Mail::to($application->email)->send(
                new AdmissionRejectedMail(
                    fullName: $application->full_name,
                    reason: $validated['remarks']
                )
            );
        } catch (\Throwable $e) {
            // Keep rejection successful even if mail fails.
        }

        return response()->json([
            'message' => 'Admission application rejected.',
        ]);
    }

    public function updateExamStatus(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'exam_status' => ['nullable', 'in:passed,failed,not_attended'],
            'exam_attendance_status' => ['nullable', 'in:attended,not_attended'],
        ]);

        if (! array_key_exists('exam_status', $validated) && ! array_key_exists('exam_attendance_status', $validated)) {
            return response()->json(['message' => 'Please provide exam_status or exam_attendance_status.'], 422);
        }

        $application = AdmissionApplication::query()->find($id);
        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $hasAttendanceColumn = Schema::hasColumn('admission_applications', 'exam_attendance_status');
        $updates = [];

        if (array_key_exists('exam_status', $validated) && $validated['exam_status'] !== null) {
            $updates['exam_status'] = $validated['exam_status'];
            if ($hasAttendanceColumn && ! array_key_exists('exam_attendance_status', $validated)) {
                $updates['exam_attendance_status'] = $validated['exam_status'] === 'not_attended' ? 'not_attended' : 'attended';
            }
        }

        if ($hasAttendanceColumn && array_key_exists('exam_attendance_status', $validated) && $validated['exam_attendance_status'] !== null) {
            $updates['exam_attendance_status'] = $validated['exam_attendance_status'];
            if ($validated['exam_attendance_status'] === 'not_attended') {
                $updates['exam_status'] = 'not_attended';
            }
        }

        if (! $hasAttendanceColumn && empty($updates) && array_key_exists('exam_attendance_status', $validated)) {
            return response()->json([
                'message' => 'Exam attendance update skipped because database column is not available yet.',
                'application' => $application->fresh(),
            ]);
        }

        if (empty($updates)) {
            return response()->json(['message' => 'No valid exam fields were provided.'], 422);
        }

        $application->update($updates);

        return response()->json([
            'message' => 'Exam status updated successfully.',
            'application' => $application->fresh(),
        ]);
    }

    public function sendExamSchedule(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:180'],
            'intro_message' => ['nullable', 'string', 'max:1000'],
            'exam_date' => ['required', 'string', 'max:120'],
            'exam_time' => ['required', 'string', 'max:120'],
            'exam_day' => ['required', 'string', 'max:60'],
            'location' => ['required', 'string', 'max:255'],
            'things_to_bring' => ['required', 'string', 'max:2000'],
            'attire_note' => ['nullable', 'string', 'max:500'],
            'additional_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $application = AdmissionApplication::query()->find($id);
        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        if (! blank($application->exam_schedule_sent_at)) {
            return response()->json([
                'message' => 'Exam schedule was already sent to this student.',
            ], 422);
        }

        if (blank($application->email)) {
            return response()->json(['message' => 'Applicant email is missing.'], 422);
        }

        $subject = trim((string) ($validated['subject'] ?? 'Entrance Exam Schedule Invitation - TCLASS'));
        $intro = trim((string) ($validated['intro_message'] ?? 'You have been invited to take the entrance examination for your application at TCLASS.'));

        $payload = [
            'subject' => $subject,
            'intro_message' => $intro,
            'exam_date' => $validated['exam_date'],
            'exam_time' => $validated['exam_time'],
            'exam_day' => $validated['exam_day'],
            'location' => $validated['location'],
            'things_to_bring' => $validated['things_to_bring'],
            'attire_note' => $validated['attire_note'] ?? null,
            'additional_note' => $validated['additional_note'] ?? null,
            'sent_by' => $request->user()?->id,
            'sent_at' => now()->toISOString(),
        ];

        try {
            Mail::to($application->email)->send(
                new EntranceExamScheduleMail(
                    fullName: $application->full_name,
                    course: (string) $application->primary_course,
                    subjectLine: $subject,
                    introMessage: $intro,
                    examDate: (string) $validated['exam_date'],
                    examTime: (string) $validated['exam_time'],
                    examDay: (string) $validated['exam_day'],
                    location: (string) $validated['location'],
                    thingsToBring: (string) $validated['things_to_bring'],
                    attireNote: $validated['attire_note'] ?? null,
                    additionalNote: $validated['additional_note'] ?? null,
                )
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to send exam schedule email. Check SMTP configuration.',
            ], 500);
        }

        $application->update([
            'exam_schedule_sent_at' => now(),
            'exam_schedule_payload' => $payload,
        ]);

        return response()->json([
            'message' => 'Exam schedule invitation sent successfully.',
            'application' => $application->fresh(),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        if ($resp = $this->assertStudent($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'current_password' => ['nullable', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        $requiresCurrent = ! (bool) $user->must_change_password;
        if ($requiresCurrent && empty($validated['current_password'])) {
            return response()->json(['message' => 'Current password is required.'], 422);
        }

        if ($requiresCurrent && ! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->must_change_password = false;
        $user->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function studentProfile(Request $request): JsonResponse
    {
        if ($resp = $this->assertStudent($request)) {
            return $resp;
        }

        $user = $request->user();
        $application = $this->latestStudentApplication($user->id);
        $formData = is_array($application?->form_data) ? $application->form_data : [];

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'student_number' => $user->student_number,
            ],
            'application' => $application ? [
                'id' => $application->id,
                'application_type' => $application->application_type,
                'primary_course' => $application->primary_course,
                'status' => $application->status,
            ] : null,
            'profile' => [
                'last_name' => (string) data_get($formData, 'lastName', ''),
                'first_name' => (string) data_get($formData, 'firstName', ''),
                'middle_name' => (string) data_get($formData, 'middleName', ''),
                'extension_name' => (string) data_get($formData, 'extensionName', ''),
                'number_street' => (string) data_get($formData, 'numberStreet', ''),
                'barangay' => (string) data_get($formData, 'barangay', ''),
                'district' => (string) data_get($formData, 'district', ''),
                'city_municipality' => (string) data_get($formData, 'cityMunicipality', ''),
                'province' => (string) data_get($formData, 'province', ''),
                'region' => (string) data_get($formData, 'region', ''),
                'email_address' => (string) data_get($formData, 'emailAddress', $application?->email ?? $user->email),
                'facebook_account' => (string) data_get($formData, 'facebookAccount', $application?->facebook_account ?? ''),
                'contact_no' => (string) data_get($formData, 'contactNo', $application?->contact_no ?? ''),
                'nationality' => (string) data_get($formData, 'nationality', ''),
                'sex' => (string) data_get($formData, 'sex', ''),
                'birthplace_city' => (string) data_get($formData, 'birthplaceCity', ''),
                'birthplace_province' => (string) data_get($formData, 'birthplaceProvince', ''),
                'birthplace_region' => (string) data_get($formData, 'birthplaceRegion', ''),
                'month_of_birth' => (string) data_get($formData, 'monthOfBirth', ''),
                'day_of_birth' => (string) data_get($formData, 'dayOfBirth', ''),
                'year_of_birth' => (string) data_get($formData, 'yearOfBirth', ''),
            ],
        ]);
    }

    public function updateStudentProfile(Request $request): JsonResponse
    {
        if ($resp = $this->assertStudent($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'extension_name' => ['nullable', 'string', 'max:255'],
            'number_street' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'city_municipality' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:255'],
            'email_address' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'facebook_account' => ['nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:120'],
            'sex' => ['nullable', 'string', 'max:20'],
            'birthplace_city' => ['nullable', 'string', 'max:255'],
            'birthplace_province' => ['nullable', 'string', 'max:255'],
            'birthplace_region' => ['nullable', 'string', 'max:255'],
            'month_of_birth' => ['nullable', 'string', 'max:2'],
            'day_of_birth' => ['nullable', 'string', 'max:2'],
            'year_of_birth' => ['nullable', 'string', 'max:4'],
        ]);

        $user = $request->user();
        $application = $this->latestStudentApplication($user->id);

        $fullName = trim(implode(' ', array_filter([
            trim((string) $validated['first_name']),
            trim((string) $validated['middle_name']),
            trim((string) $validated['last_name']),
            trim((string) $validated['extension_name']),
        ])));
        $normalizedEmail = strtolower(trim((string) $validated['email_address']));

        $user->name = $fullName;
        $user->email = $normalizedEmail;
        $user->save();

        if ($application) {
            $formData = is_array($application->form_data) ? $application->form_data : [];
            $formData['lastName'] = trim((string) $validated['last_name']);
            $formData['firstName'] = trim((string) $validated['first_name']);
            $formData['middleName'] = trim((string) ($validated['middle_name'] ?? ''));
            $formData['extensionName'] = trim((string) ($validated['extension_name'] ?? ''));
            $formData['numberStreet'] = trim((string) $validated['number_street']);
            $formData['barangay'] = trim((string) $validated['barangay']);
            $formData['district'] = trim((string) $validated['district']);
            $formData['cityMunicipality'] = trim((string) $validated['city_municipality']);
            $formData['province'] = trim((string) $validated['province']);
            $formData['region'] = trim((string) $validated['region']);
            $formData['emailAddress'] = $normalizedEmail;
            $formData['facebookAccount'] = trim((string) ($validated['facebook_account'] ?? ''));
            $formData['contactNo'] = trim((string) ($validated['contact_no'] ?? ''));
            $formData['nationality'] = trim((string) ($validated['nationality'] ?? ''));
            $formData['sex'] = trim((string) ($validated['sex'] ?? ''));
            $formData['birthplaceCity'] = trim((string) ($validated['birthplace_city'] ?? ''));
            $formData['birthplaceProvince'] = trim((string) ($validated['birthplace_province'] ?? ''));
            $formData['birthplaceRegion'] = trim((string) ($validated['birthplace_region'] ?? ''));
            $formData['monthOfBirth'] = trim((string) ($validated['month_of_birth'] ?? ''));
            $formData['dayOfBirth'] = trim((string) ($validated['day_of_birth'] ?? ''));
            $formData['yearOfBirth'] = trim((string) ($validated['year_of_birth'] ?? ''));

            $application->full_name = $fullName;
            $application->email = $normalizedEmail;
            $application->facebook_account = trim((string) ($validated['facebook_account'] ?? ''));
            $application->contact_no = trim((string) ($validated['contact_no'] ?? ''));
            $application->form_data = $formData;
            $application->save();
        }

        return response()->json([
            'message' => 'Student profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'student_number' => $user->student_number,
                'must_change_password' => (bool) $user->must_change_password,
            ],
            'application' => $application ? [
                'id' => $application->id,
                'application_type' => $application->application_type,
                'primary_course' => $application->primary_course,
                'status' => $application->status,
            ] : null,
            'profile' => [
                'last_name' => trim((string) $validated['last_name']),
                'first_name' => trim((string) $validated['first_name']),
                'middle_name' => trim((string) ($validated['middle_name'] ?? '')),
                'extension_name' => trim((string) ($validated['extension_name'] ?? '')),
                'number_street' => trim((string) $validated['number_street']),
                'barangay' => trim((string) $validated['barangay']),
                'district' => trim((string) $validated['district']),
                'city_municipality' => trim((string) $validated['city_municipality']),
                'province' => trim((string) $validated['province']),
                'region' => trim((string) $validated['region']),
                'email_address' => $normalizedEmail,
                'facebook_account' => trim((string) ($validated['facebook_account'] ?? '')),
                'contact_no' => trim((string) ($validated['contact_no'] ?? '')),
                'nationality' => trim((string) ($validated['nationality'] ?? '')),
                'sex' => trim((string) ($validated['sex'] ?? '')),
                'birthplace_city' => trim((string) ($validated['birthplace_city'] ?? '')),
                'birthplace_province' => trim((string) ($validated['birthplace_province'] ?? '')),
                'birthplace_region' => trim((string) ($validated['birthplace_region'] ?? '')),
                'month_of_birth' => trim((string) ($validated['month_of_birth'] ?? '')),
                'day_of_birth' => trim((string) ($validated['day_of_birth'] ?? '')),
                'year_of_birth' => trim((string) ($validated['year_of_birth'] ?? '')),
            ],
        ]);
    }

    public function passwordReminder(Request $request): JsonResponse
    {
        if ($resp = $this->assertStudent($request)) {
            return $resp;
        }

        return response()->json([
            'must_change_password' => (bool) $request->user()->must_change_password,
        ]);
    }

    private function generateStudentNumber(): string
    {
        do {
            $year = now()->format('y');
            $number = $year . '-1-1-' . random_int(1000, 9999);
        } while (User::query()->where('student_number', $number)->exists());

        return $number;
    }

    private function generateTemporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}

