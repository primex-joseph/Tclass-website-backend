<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdmissionApprovedMail;
use App\Mail\AdmissionRejectedMail;
use App\Models\AdmissionApplication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdmissionController extends Controller
{
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
            'form_data' => ['nullable'],
            'id_picture' => ['nullable', 'image', 'max:4096'],
            'one_by_one_picture' => ['nullable', 'image', 'max:4096'],
            'right_thumbmark' => ['nullable', 'image', 'max:4096'],
            'birth_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'valid_id_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
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
        if (! is_numeric($age) || (int) $age < 12 || (int) $age > 90) {
            return response()->json(['message' => 'Age must be between 12 and 90.'], 422);
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
        $applicationType = $validated['application_type'] ?? 'admission';
        $validIdType = $validated['valid_id_type'] ?? data_get($formData, 'validIdType');
        $normalizedEmail = Str::lower($validated['email']);

        if ($applicationType === 'vocational') {
            if (! $request->hasFile('birth_certificate')) {
                return response()->json(['message' => 'Birth certificate upload is required.'], 422);
            }
            if (trim((string) $validIdType) === '') {
                return response()->json(['message' => 'Valid ID type is required.'], 422);
            }
            if (! $request->hasFile('valid_id_image')) {
                return response()->json(['message' => 'Valid ID upload is required.'], 422);
            }
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
