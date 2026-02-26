<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminCurriculumController extends Controller
{
    private function ensureRole(int $userId, string $role): bool
    {
        return DB::table('portal_user_roles')
            ->where('user_id', $userId)
            ->where('role', $role)
            ->where('is_active', 1)
            ->exists();
    }

    private function assertAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $this->ensureRole($user->id, 'admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return null;
    }

    private function normalizeProgramKey(?string $programName): string
    {
        $value = trim((string) $programName);
        if ($value === '') {
            return 'GENERAL';
        }

        $value = Str::upper(Str::slug($value, '_'));
        return $value !== '' ? $value : 'GENERAL';
    }

    private function decodeRows(Request $request): array
    {
        $raw = $request->input('rows');
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function syncVersionToCourses(int $curriculumVersionId): void
    {
        $version = DB::table('curriculum_versions')->where('id', $curriculumVersionId)->first();
        if (! $version) {
            return;
        }

        $subjects = DB::table('curriculum_subjects')
            ->where('curriculum_version_id', $curriculumVersionId)
            ->orderBy('year_level')
            ->orderBy('semester')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $now = now();
        $programKey = (string) $version->program_key;

        if ($subjects->isEmpty()) {
            DB::table('courses')
                ->where('program_key', $programKey)
                ->update([
                    'is_active' => 0,
                    'curriculum_version_id' => $curriculumVersionId,
                    'updated_at' => $now,
                ]);
            return;
        }

        $upsertRows = $subjects->map(function ($subject) use ($programKey, $curriculumVersionId, $now) {
            return [
                'code' => (string) $subject->code,
                'program_key' => $programKey,
                'curriculum_version_id' => $curriculumVersionId,
                'title' => (string) $subject->title,
                'description' => null,
                'units' => (float) $subject->units,
                'tf' => (float) $subject->tf,
                'lec' => (int) $subject->lec,
                'lab' => (int) $subject->lab,
                'schedule' => $subject->schedule,
                'section' => $subject->section,
                'room' => $subject->room,
                'instructor' => $subject->instructor,
                'year_level' => (int) $subject->year_level,
                'semester' => (int) $subject->semester,
                'prerequisite_code' => $subject->prerequisite_code,
                'is_active' => 1,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        })->all();

        DB::table('courses')->upsert(
            $upsertRows,
            ['program_key', 'code'],
            [
                'curriculum_version_id',
                'title',
                'description',
                'units',
                'tf',
                'lec',
                'lab',
                'schedule',
                'section',
                'room',
                'instructor',
                'year_level',
                'semester',
                'prerequisite_code',
                'is_active',
                'updated_at',
            ]
        );

        $importedCodes = $subjects->pluck('code')->map(fn ($v) => (string) $v)->values()->all();

        DB::table('courses')
            ->where('program_key', $programKey)
            ->whereNotIn('code', $importedCodes)
            ->update([
                'is_active' => 0,
                'curriculum_version_id' => $curriculumVersionId,
                'updated_at' => $now,
            ]);

        $courseMap = DB::table('courses')
            ->where('program_key', $programKey)
            ->pluck('id', 'code');

        foreach ($subjects as $subject) {
            $courseId = $courseMap[$subject->code] ?? null;
            if (! $courseId) {
                continue;
            }

            $prereqCourseId = null;
            if ($subject->prerequisite_code) {
                $prereqCourseId = $courseMap[$subject->prerequisite_code] ?? null;
            }

            DB::table('courses')
                ->where('id', $courseId)
                ->update([
                    'prerequisite_course_id' => $prereqCourseId,
                    'updated_at' => $now,
                ]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $versions = DB::table('curriculum_versions')
            ->leftJoin('users as u', 'u.id', '=', 'curriculum_versions.uploaded_by')
            ->select(
                'curriculum_versions.id',
                'curriculum_versions.program_key',
                'curriculum_versions.program_name',
                'curriculum_versions.label',
                'curriculum_versions.effective_ay',
                'curriculum_versions.version',
                'curriculum_versions.source_file_name',
                'curriculum_versions.source_file_path',
                'curriculum_versions.notes',
                'curriculum_versions.is_active',
                'curriculum_versions.created_at',
                'curriculum_versions.updated_at',
                'u.name as uploaded_by_name'
            )
            ->orderByDesc('curriculum_versions.id')
            ->get()
            ->map(function ($row) {
                $subjectCount = DB::table('curriculum_subjects')
                    ->where('curriculum_version_id', $row->id)
                    ->count();

                return [
                    'id' => $row->id,
                    'program_key' => $row->program_key,
                    'program_name' => $row->program_name,
                    'label' => $row->label,
                    'effective_ay' => $row->effective_ay,
                    'version' => $row->version,
                    'source_file_name' => $row->source_file_name,
                    'source_file_path' => $row->source_file_path,
                    'notes' => $row->notes,
                    'is_active' => (bool) $row->is_active,
                    'subject_count' => $subjectCount,
                    'uploaded_by_name' => $row->uploaded_by_name,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            })
            ->values();

        return response()->json(['curricula' => $versions]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $rows = $this->decodeRows($request);

        $validated = $request->validate([
            'program_name' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'effective_ay' => ['nullable', 'string', 'max:50'],
            'version' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'activate' => ['nullable', 'boolean'],
            'curriculum_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if (count($rows) === 0) {
            return response()->json([
                'message' => 'Curriculum subjects are required. Add subject rows before uploading.',
            ], 422);
        }

        $normalizedRows = [];
        foreach ($rows as $index => $row) {
            $code = trim((string) data_get($row, 'code'));
            $title = trim((string) data_get($row, 'title'));
            $yearLevel = (int) data_get($row, 'year_level');
            $semester = (int) data_get($row, 'semester');
            $units = (float) data_get($row, 'units', 3);

            if ($code === '' || $title === '' || $yearLevel < 1 || $semester < 1) {
                return response()->json([
                    'message' => "Invalid curriculum row at index {$index}.",
                ], 422);
            }

            $normalizedRows[] = [
                'year_level' => $yearLevel,
                'semester' => $semester,
                'code' => $code,
                'title' => $title,
                'units' => $units > 0 ? $units : 3,
                'tf' => (float) data_get($row, 'tf', 1500),
                'lec' => (int) data_get($row, 'lec', 3),
                'lab' => (int) data_get($row, 'lab', 0),
                'schedule' => ($v = trim((string) data_get($row, 'schedule'))) !== '' ? $v : null,
                'section' => ($v = trim((string) data_get($row, 'section'))) !== '' ? $v : null,
                'room' => ($v = trim((string) data_get($row, 'room'))) !== '' ? $v : null,
                'instructor' => ($v = trim((string) data_get($row, 'instructor'))) !== '' ? $v : null,
                'prerequisite_code' => ($v = trim((string) data_get($row, 'prerequisite_code'))) !== '' ? $v : null,
                'sort_order' => (int) data_get($row, 'sort_order', $index + 1),
            ];
        }

        $programName = trim($validated['program_name']);
        $programKey = $this->normalizeProgramKey($programName);
        $userId = $request->user()->id;
        $activate = (bool) ($validated['activate'] ?? false);

        $path = null;
        $sourceFileName = null;
        if ($request->hasFile('curriculum_file')) {
            $file = $request->file('curriculum_file');
            $path = $file->store('curricula', 'public');
            $sourceFileName = $file->getClientOriginalName();
        }

        $versionId = DB::transaction(function () use ($validated, $normalizedRows, $programName, $programKey, $userId, $path, $sourceFileName, $activate) {
            if ($activate) {
                DB::table('curriculum_versions')
                    ->where('program_key', $programKey)
                    ->update(['is_active' => 0, 'updated_at' => now()]);
            }

            $versionId = DB::table('curriculum_versions')->insertGetId([
                'program_key' => $programKey,
                'program_name' => $programName,
                'label' => trim($validated['label']),
                'effective_ay' => $validated['effective_ay'] ?? null,
                'version' => $validated['version'] ?? 'v1',
                'source_file_name' => $sourceFileName,
                'source_file_path' => $path,
                'notes' => $validated['notes'] ?? null,
                'is_active' => $activate ? 1 : 0,
                'uploaded_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $subjectRows = array_map(function ($row) use ($versionId) {
                return [
                    'curriculum_version_id' => $versionId,
                    ...$row,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $normalizedRows);

            DB::table('curriculum_subjects')->insert($subjectRows);

            return $versionId;
        });

        if ($activate) {
            $this->syncVersionToCourses($versionId);
        }

        return response()->json([
            'message' => $activate
                ? 'Curriculum uploaded and activated successfully.'
                : 'Curriculum uploaded successfully.',
            'curriculum_id' => $versionId,
        ], 201);
    }

    public function activate(Request $request, int $curriculumId): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $version = DB::table('curriculum_versions')->where('id', $curriculumId)->first();
        if (! $version) {
            return response()->json(['message' => 'Curriculum version not found.'], 404);
        }

        DB::transaction(function () use ($version, $curriculumId) {
            DB::table('curriculum_versions')
                ->where('program_key', $version->program_key)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            DB::table('curriculum_versions')
                ->where('id', $curriculumId)
                ->update(['is_active' => 1, 'updated_at' => now()]);
        });

        $this->syncVersionToCourses($curriculumId);

        return response()->json(['message' => 'Curriculum activated successfully.']);
    }
}

