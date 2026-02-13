<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
}
