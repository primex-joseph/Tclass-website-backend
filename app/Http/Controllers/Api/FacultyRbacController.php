<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FacultyWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class FacultyRbacController extends Controller
{
    private function ensureAdmin(Request $request): ?JsonResponse
    {
        $isAdmin = DB::table('portal_user_roles')
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->where('is_active', 1)
            ->exists();

        if (! $isAdmin) {
            return response()->json(['message' => 'Forbidden. Admin role required.'], 403);
        }

        return null;
    }

    public function positions(Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }

        $positions = DB::table('faculty_positions')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'title' => (string) $row->title,
                'category' => (string) ($row->category ?? ''),
                'template_hint' => FacultyWorkflow::templateFromPosition((string) $row->title),
            ])
            ->values();

        return response()->json(['positions' => $positions]);
    }

    public function index(Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }

        FacultyWorkflow::syncPermissionCatalog();

        $users = DB::table('users')
            ->join('portal_user_roles', function ($join) {
                $join->on('portal_user_roles.user_id', '=', 'users.id')
                    ->where('portal_user_roles.role', '=', 'faculty')
                    ->where('portal_user_roles.is_active', '=', 1);
            })
            ->leftJoin('faculty_profiles as fp', 'fp.user_id', '=', 'users.id')
            ->leftJoin('faculty_positions as pos', 'pos.id', '=', 'fp.position_id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'fp.employee_id',
                'fp.department',
                'fp.position_id',
                'pos.title as position_title'
            )
            ->orderBy('users.name')
            ->get()
            ->map(function ($row) {
                $user = User::query()->find((int) $row->id);
                if (! $user) {
                    return null;
                }

                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'email' => (string) $row->email,
                    'employee_id' => (string) ($row->employee_id ?? ''),
                    'department' => (string) ($row->department ?? ''),
                    'position_id' => $row->position_id ? (int) $row->position_id : null,
                    'position' => (string) ($row->position_title ?? ''),
                    'template' => FacultyWorkflow::currentTemplate($user),
                    'effective_permissions' => FacultyWorkflow::effectivePermissions($user),
                    'overrides' => FacultyWorkflow::overrideRowsForUser((int) $row->id),
                ];
            })
            ->filter()
            ->values();

        $templates = collect(FacultyWorkflow::templateCatalog())
            ->map(function ($template) {
                $role = Role::findByName($template['key'], 'web');

                return [
                    'key' => $template['key'],
                    'label' => $template['label'],
                    'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                ];
            })
            ->values();

        return response()->json([
            'permissions' => FacultyWorkflow::permissionCatalog(),
            'templates' => $templates,
            'users' => $users,
        ]);
    }

    public function updateTemplate(Request $request, string $templateKey): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }

        $allowedTemplateKeys = collect(FacultyWorkflow::templateCatalog())->pluck('key')->all();
        if (! in_array($templateKey, $allowedTemplateKeys, true)) {
            return response()->json(['message' => 'Template not found.'], 404);
        }

        $validated = $request->validate([
            'permission_keys' => ['required', 'array'],
            'permission_keys.*' => ['required', 'string'],
        ]);

        $allowedPermissions = FacultyWorkflow::permissionKeys()->all();
        $permissionKeys = collect($validated['permission_keys'])
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value) => in_array($value, $allowedPermissions, true))
            ->values()
            ->all();

        $role = Role::findByName($templateKey, 'web');
        $role->syncPermissions($permissionKeys);

        return response()->json(['message' => 'Template updated successfully.']);
    }

    public function updateUser(Request $request, int $userId): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $validated = $request->validate([
            'template' => ['nullable', 'string'],
            'overrides' => ['nullable', 'array'],
            'overrides.*.permission_name' => ['required', 'string'],
            'overrides.*.is_allowed' => ['required', 'boolean'],
        ]);

        $template = (string) ($validated['template'] ?? '');
        if ($template !== '') {
            FacultyWorkflow::assignTemplate($user, $template);
        }

        $allowedPermissions = FacultyWorkflow::permissionKeys()->all();
        DB::table('faculty_permission_overrides')->where('user_id', $userId)->delete();

        foreach ($validated['overrides'] ?? [] as $override) {
            $permissionName = (string) $override['permission_name'];
            if (! in_array($permissionName, $allowedPermissions, true)) {
                continue;
            }

            DB::table('faculty_permission_overrides')->updateOrInsert(
                ['user_id' => $userId, 'permission_name' => $permissionName],
                [
                    'is_allowed' => (bool) $override['is_allowed'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return response()->json(['message' => 'Faculty RBAC settings updated successfully.']);
    }
}
