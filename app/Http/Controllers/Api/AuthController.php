<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        // Generate reset token
        $token = Str::random(64);
        
        // Store token in password_resets table (create if not exists)
        DB::table('password_resets')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        // Send email with reset link (for now, just return the token in response for testing)
        // In production, send actual email
        $resetUrl = url("/reset-password?token={$token}&email=" . urlencode($user->email));

        return response()->json([
            'message' => 'Password reset link has been sent to your email.',
            'reset_url' => $resetUrl, // Remove this in production, for testing only
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Verify token
        $resetRecord = DB::table('password_resets')
            ->where('email', $validated['email'])
            ->first();

        if (! $resetRecord || ! Hash::check($validated['token'], $resetRecord->token)) {
            return response()->json([
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        // Check if token is expired (60 minutes)
        if (now()->diffInMinutes($resetRecord->created_at) > 60) {
            return response()->json([
                'message' => 'Reset token has expired. Please request a new one.',
            ], 400);
        }

        // Update password
        $user = User::query()->where('email', $validated['email'])->first();
        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        // Delete used token
        DB::table('password_resets')->where('email', $validated['email'])->delete();

        return response()->json([
            'message' => 'Password has been reset successfully. You can now log in with your new password.',
        ]);
    }
}
