<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FacultyWorkflow
{
    public const TEMPLATE_DEFAULT = 'faculty-template.default';
    public const TEMPLATE_INSTRUCTOR = 'faculty-template.instructor';
    public const TEMPLATE_REGISTRAR = 'faculty-template.registrar';

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public static function permissionCatalog(): array
    {
        return [
            ['key' => 'faculty.schedules.view', 'label' => 'View schedules', 'description' => 'Access faculty schedule data and calendar views.'],
            ['key' => 'faculty.schedules.manage', 'label' => 'Manage schedules', 'description' => 'Create and update schedule records across faculty offerings.'],
            ['key' => 'faculty.schedules.export', 'label' => 'Export schedules', 'description' => 'Print or export schedule data.'],
            ['key' => 'faculty.class_lists.view', 'label' => 'View class lists', 'description' => 'Access faculty class list records.'],
            ['key' => 'faculty.class_lists.export', 'label' => 'Export class lists', 'description' => 'Print or export class list data.'],
            ['key' => 'faculty.students.view', 'label' => 'View students', 'description' => 'Review students attached to assigned offerings.'],
            ['key' => 'faculty.assignments.view', 'label' => 'View assignments', 'description' => 'Access assignment records for visible offerings.'],
            ['key' => 'faculty.assignments.manage', 'label' => 'Manage assignments', 'description' => 'Create, edit, publish, and remove assignments.'],
            ['key' => 'faculty.grade_sheets.view', 'label' => 'View grade sheets', 'description' => 'Open grade sheets and posting history.'],
            ['key' => 'faculty.grade_sheets.post', 'label' => 'Post grade sheets', 'description' => 'Save and post grade sheet entries.'],
            ['key' => 'faculty.grades.view', 'label' => 'View grades', 'description' => 'Access grade summaries and per-student grades.'],
            ['key' => 'faculty.grades.manage', 'label' => 'Manage grades', 'description' => 'Update grade entry values before posting.'],
            ['key' => 'faculty.syllabi.view', 'label' => 'View syllabi', 'description' => 'Open syllabus files linked to classes.'],
            ['key' => 'faculty.syllabi.upload', 'label' => 'Upload syllabi', 'description' => 'Upload and replace syllabus files for owned classes.'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function templatePermissions(): array
    {
        return [
            self::TEMPLATE_DEFAULT => [
                'faculty.schedules.view',
                'faculty.class_lists.view',
                'faculty.students.view',
                'faculty.assignments.view',
                'faculty.grade_sheets.view',
                'faculty.grades.view',
                'faculty.syllabi.view',
            ],
            self::TEMPLATE_INSTRUCTOR => [
                'faculty.schedules.view',
                'faculty.class_lists.view',
                'faculty.students.view',
                'faculty.assignments.view',
                'faculty.assignments.manage',
                'faculty.grade_sheets.view',
                'faculty.grade_sheets.post',
                'faculty.grades.view',
                'faculty.grades.manage',
                'faculty.syllabi.view',
                'faculty.syllabi.upload',
            ],
            self::TEMPLATE_REGISTRAR => [
                'faculty.schedules.view',
                'faculty.schedules.manage',
                'faculty.schedules.export',
                'faculty.class_lists.view',
                'faculty.class_lists.export',
                'faculty.students.view',
                'faculty.assignments.view',
                'faculty.grade_sheets.view',
                'faculty.grade_sheets.post',
                'faculty.grades.view',
                'faculty.syllabi.view',
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function templateCatalog(): array
    {
        return [
            ['key' => self::TEMPLATE_DEFAULT, 'label' => 'Faculty Default'],
            ['key' => self::TEMPLATE_INSTRUCTOR, 'label' => 'Instructor'],
            ['key' => self::TEMPLATE_REGISTRAR, 'label' => 'Registrar'],
        ];
    }

    public static function syncPermissionCatalog(): void
    {
        foreach (self::permissionCatalog() as $permissionRow) {
            Permission::findOrCreate($permissionRow['key'], 'web');
        }

        foreach (self::templatePermissions() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }

    public static function templateFromPosition(?string $positionTitle): string
    {
        $value = Str::lower(trim((string) $positionTitle));
        if ($value === '') {
            return self::TEMPLATE_DEFAULT;
        }

        if (str_contains($value, 'registrar')) {
            return self::TEMPLATE_REGISTRAR;
        }

        if (
            str_contains($value, 'instructor') ||
            str_contains($value, 'professor') ||
            str_contains($value, 'lecturer')
        ) {
            return self::TEMPLATE_INSTRUCTOR;
        }

        return self::TEMPLATE_DEFAULT;
    }

    public static function assignTemplate(User $user, string $templateName): void
    {
        $templateNames = array_keys(self::templatePermissions());
        $user->syncRoles(
            collect($user->roles)
                ->pluck('name')
                ->filter(fn (string $roleName) => ! in_array($roleName, $templateNames, true))
                ->push($templateName)
                ->values()
                ->all()
        );
    }

    /**
     * @return array<int, string>
     */
    public static function effectivePermissions(User $user): array
    {
        $base = collect($user->getAllPermissions()->pluck('name')->all());
        $overrides = DB::table('faculty_permission_overrides')
            ->where('user_id', $user->id)
            ->get(['permission_name', 'is_allowed']);

        $denied = $overrides
            ->where('is_allowed', 0)
            ->pluck('permission_name')
            ->all();

        $allowed = $overrides
            ->where('is_allowed', 1)
            ->pluck('permission_name')
            ->all();

        return $base
            ->reject(fn (string $permission) => in_array($permission, $denied, true))
            ->merge($allowed)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function hasPermission(User $user, string $permission): bool
    {
        return in_array($permission, self::effectivePermissions($user), true);
    }

    /**
     * @return array<int, array{permission_name: string, is_allowed: bool}>
     */
    public static function overrideRowsForUser(int $userId): array
    {
        return DB::table('faculty_permission_overrides')
            ->where('user_id', $userId)
            ->orderBy('permission_name')
            ->get(['permission_name', 'is_allowed'])
            ->map(fn ($row) => [
                'permission_name' => (string) $row->permission_name,
                'is_allowed' => (bool) $row->is_allowed,
            ])
            ->all();
    }

    public static function currentTemplate(User $user): ?string
    {
        $templateNames = array_keys(self::templatePermissions());

        return $user->roles
            ->pluck('name')
            ->first(fn (string $name) => in_array($name, $templateNames, true));
    }

    /**
     * @return Collection<int, string>
     */
    public static function permissionKeys(): Collection
    {
        return collect(self::permissionCatalog())->pluck('key');
    }
}
