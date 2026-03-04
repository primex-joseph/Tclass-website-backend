<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'role' => ['required', Rule::in(['student', 'faculty', 'admin'])],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->orWhere('student_number', $validated['email'])
            ->orWhere('name', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $hasRole = DB::table('portal_user_roles')
            ->where('user_id', $user->id)
            ->where('role', $validated['role'])
            ->where('is_active', 1)
            ->exists();

        if (! $hasRole) {
            return response()->json([
                'message' => 'Unauthorized role access.',
            ], 403);
        }

        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'role' => $validated['role'],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'student_number' => $user->student_number,
                'role' => $validated['role'],
                'must_change_password' => (bool) $user->must_change_password,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $role = DB::table('portal_user_roles')
            ->where('user_id', $user->id)
            ->where('is_active', 1)
            ->value('role');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'student_number' => $user->student_number,
                'role' => $role,
                'must_change_password' => (bool) $user->must_change_password,
            ],
        ]);
    }

    public function checkForgotPasswordEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'No email address found.',
                'exists' => false,
            ], 404);
        }

        return response()->json([
            'message' => 'Email address found.',
            'exists' => true,
            'email' => $user->email,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'No email address found.',
            ], 404);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_resets')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        Mail::html(
            view('emails.password-reset-code', [
                'name' => $user->name,
                'code' => $code,
                'expiresInMinutes' => 10,
            ])->render(),
            function ($message) use ($user): void {
                $message->to($user->email, $user->name)
                    ->subject('Your TCLASS password reset code');
            }
        );

        return response()->json([
            'message' => 'A 6-digit verification code has been sent to your email.',
            'email' => $user->email,
            'expires_in_minutes' => 10,
        ]);
    }

    public function verifyResetCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $resetRecord = DB::table('password_resets')
            ->where('email', $validated['email'])
            ->first();

        if (! $resetRecord) {
            return response()->json([
                'message' => 'No reset request found for this email.',
            ], 404);
        }

        if (now()->diffInMinutes($resetRecord->created_at) > 10) {
            DB::table('password_resets')->where('email', $validated['email'])->delete();

            return response()->json([
                'message' => 'The verification code has expired. Please request a new one.',
            ], 400);
        }

        if (! Hash::check($validated['code'], $resetRecord->token)) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Code verified successfully.',
            'verified' => true,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $resetRecord = DB::table('password_resets')
            ->where('email', $validated['email'])
            ->first();

        if (! $resetRecord) {
            return response()->json([
                'message' => 'No reset request found for this email.',
            ], 404);
        }

        if (now()->diffInMinutes($resetRecord->created_at) > 10) {
            DB::table('password_resets')->where('email', $validated['email'])->delete();

            return response()->json([
                'message' => 'The verification code has expired. Please request a new one.',
            ], 400);
        }

        if (! Hash::check($validated['code'], $resetRecord->token)) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'No email address found.',
            ], 404);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        DB::table('password_resets')->where('email', $validated['email'])->delete();

        return response()->json([
            'message' => 'Password changed successfully. You can now log in with your new password.',
        ]);
    }
}
