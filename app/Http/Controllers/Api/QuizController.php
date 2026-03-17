<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Models\ProgramCatalog;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuizController extends Controller
{
    private function currentRole(Request $request): ?string
    {
        return DB::table('portal_user_roles')
            ->where('user_id', $request->user()->id)
            ->where('is_active', 1)
            ->value('role');
    }

    private function assertScopeRole(Request $request, string $scope): ?JsonResponse
    {
        $role = $this->currentRole($request);
        if ($scope === 'admin' && $role !== 'admin') {
            return response()->json(['message' => 'Forbidden. Admin role required.'], 403);
        }
        if ($scope === 'faculty' && $role !== 'faculty') {
            return response()->json(['message' => 'Forbidden. Faculty role required.'], 403);
        }

        return null;
    }

    private function assertStudentRole(Request $request): ?JsonResponse
    {
        if ($this->currentRole($request) !== 'student') {
            return response()->json(['message' => 'Forbidden. Student role required.'], 403);
        }

        return null;
    }

    private function frontendBaseUrl(): string
    {
        $configured = trim((string) env('FRONTEND_URL', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return 'http://localhost:3000';
    }

    private function buildShareLink(string $token): string
    {
        return $this->frontendBaseUrl() . '/student/quizzes/' . $token;
    }

    private function generateShareToken(): string
    {
        return 'quiz_' . Str::lower(Str::random(32));
    }

    private function normalizeChoiceId(string $value, int $index = 0): string
    {
        $trimmed = strtoupper(trim($value));
        if ($trimmed === '') {
            return chr(65 + $index);
        }
        return preg_replace('/[^A-Z0-9_-]/', '', $trimmed) ?: chr(65 + $index);
    }

    private function normalizeChoices(array $choices): array
    {
        $normalized = [];
        foreach (array_values($choices) as $index => $choice) {
            $id = $this->normalizeChoiceId((string) ($choice['id'] ?? ''), $index);
            $text = trim((string) ($choice['text'] ?? ''));
            if ($text === '') {
                $text = 'Choice ' . ($index + 1);
            }
            $normalized[] = [
                'id' => $id,
                'text' => $text,
            ];
        }

        return $normalized;
    }

    private function loadCreatorNames(Collection $quizzes): array
    {
        $creatorIds = $quizzes->pluck('created_by_user_id')->filter()->unique()->values();
        if ($creatorIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $creatorIds)
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(function (User $user) {
                $name = trim((string) $user->name);
                if ($name === '') {
                    $name = trim((string) $user->email);
                }
                if ($name === '') {
                    $name = 'Portal User';
                }

                return [$user->id => $name];
            })
            ->all();
    }

    private function loadProgramTitles(Collection $quizzes): array
    {
        $programIds = $quizzes->pluck('course_program_id')->filter()->unique()->values();
        if ($programIds->isEmpty()) {
            return [];
        }

        return ProgramCatalog::query()
            ->whereIn('id', $programIds)
            ->get(['id', 'title'])
            ->mapWithKeys(fn (ProgramCatalog $program) => [$program->id => (string) $program->title])
            ->all();
    }

    private function loadOfferingMetadata(Collection $quizzes): array
    {
        $offeringIds = $quizzes->pluck('offering_id')->filter()->unique()->values();
        if ($offeringIds->isEmpty()) {
            return [];
        }

        return DB::table('class_offerings as o')
            ->leftJoin('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('enrollment_periods as ep', 'ep.id', '=', 'o.period_id')
            ->whereIn('o.id', $offeringIds)
            ->select([
                'o.id as offering_id',
                'c.code as course_code',
                'c.title as course_title',
                'c.year_level',
                'c.semester',
                'c.program_key',
                'ss.section_code',
                'ss.program_name as section_program_name',
                'ep.id as period_id',
                'ep.name as period_name',
            ])
            ->get()
            ->mapWithKeys(function ($row) {
                $code = trim((string) ($row->course_code ?? ''));
                $title = trim((string) ($row->course_title ?? 'Subject'));
                $section = trim((string) ($row->section_code ?? ''));
                $label = trim(($code !== '' ? $code . ' - ' : '') . $title . ($section !== '' ? ' (' . $section . ')' : ''));
                $programName = trim((string) ($row->section_program_name ?? ''));
                if ($programName === '') {
                    $programKey = trim((string) ($row->program_key ?? ''));
                    if ($programKey !== '') {
                        $programName = Str::of($programKey)->replace('_', ' ')->title()->value();
                    }
                }

                return [
                    (int) $row->offering_id => [
                        'offering_label' => $label !== '' ? $label : 'Unassigned Offering',
                        'course_code' => $code,
                        'course_title' => $title,
                        'section_code' => $section,
                        'program_name' => $programName !== '' ? $programName : 'Unassigned Program',
                        'year_level' => $row->year_level === null ? null : (int) $row->year_level,
                        'semester' => $row->semester === null ? null : (int) $row->semester,
                        'period_id' => $row->period_id === null ? null : (int) $row->period_id,
                        'period_name' => $row->period_name ? (string) $row->period_name : '',
                    ],
                ];
            })
            ->all();
    }

    private function quizRow(Quiz $quiz, array $offeringMeta, array $creatorNames, array $programTitles): array
    {
        $offering = $quiz->offering_id ? ($offeringMeta[$quiz->offering_id] ?? null) : null;
        $creatorName = $quiz->created_by_user_id ? ($creatorNames[$quiz->created_by_user_id] ?? null) : null;
        $programLabel = $quiz->course_program_id ? ($programTitles[$quiz->course_program_id] ?? null) : null;

        if (! $programLabel) {
            $programLabel = $offering['program_name'] ?? 'Unassigned Program';
        }

        return [
            'id' => (int) $quiz->id,
            'title' => (string) $quiz->title,
            'instructions' => (string) ($quiz->instructions ?? ''),
            'pass_rate' => (int) $quiz->pass_rate,
            'duration_minutes' => (int) $quiz->duration_minutes,
            'status' => (string) $quiz->status,
            'quiz_type' => (string) $quiz->quiz_type,
            'created_by_user_id' => $quiz->created_by_user_id === null ? null : (int) $quiz->created_by_user_id,
            'created_by_name' => $creatorName ?: 'Portal User',
            'offering_id' => $quiz->offering_id === null ? null : (int) $quiz->offering_id,
            'offering_label' => $offering['offering_label'] ?? 'Unassigned Offering',
            'course_program_id' => $quiz->course_program_id === null ? null : (int) $quiz->course_program_id,
            'course_program_label' => $programLabel,
            'shuffle_items' => (bool) $quiz->shuffle_items,
            'shuffle_choices' => (bool) $quiz->shuffle_choices,
            'published_at' => $quiz->published_at?->toISOString(),
            'expires_at' => $quiz->expires_at?->toISOString(),
            'share_token' => $quiz->share_token,
            'link_url' => $quiz->share_token ? $this->buildShareLink($quiz->share_token) : null,
            'invited_admission_ids' => array_values(array_unique(array_map('intval', $quiz->invited_admission_ids ?? []))),
            'invited_recipient_emails' => array_values(array_unique(array_map('strval', $quiz->invited_recipient_emails ?? []))),
            'created_at' => $quiz->created_at?->toISOString(),
            'updated_at' => $quiz->updated_at?->toISOString(),
        ];
    }

    private function findQuizForScope(string $scope, int $id): ?Quiz
    {
        return Quiz::query()->where('scope', $scope)->where('id', $id)->first();
    }

    private function scopeQuizQuery(string $scope)
    {
        return Quiz::query()->where('scope', $scope);
    }

    private function facultyTeacherId(User $user): ?int
    {
        $teacherId = DB::table('faculty_profiles')->where('user_id', $user->id)->value('schedule_teacher_id');
        if ($teacherId) {
            return (int) $teacherId;
        }

        $fallback = DB::table('schedule_teachers')->where('user_id', $user->id)->value('id');
        return $fallback ? (int) $fallback : null;
    }

    public function index(Request $request, string $scope): JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'quiz_type' => ['nullable', Rule::in(['regular', 'entrance'])],
            'offering_id' => ['nullable', 'integer', 'min:1'],
            'created_by' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = $this->scopeQuizQuery($scope);

        if ($scope === 'faculty') {
            $query->where('created_by_user_id', $request->user()->id);
        }

        $q = trim((string) ($validated['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($nested) use ($q) {
                $nested
                    ->where('title', 'like', '%' . $q . '%')
                    ->orWhere('instructions', 'like', '%' . $q . '%');
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['quiz_type'])) {
            $query->where('quiz_type', $validated['quiz_type']);
        }
        if (! empty($validated['offering_id'])) {
            $query->where('offering_id', (int) $validated['offering_id']);
        }
        if (! empty($validated['created_by'])) {
            $query->where('created_by_user_id', (int) $validated['created_by']);
        }

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);

        $paginator = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items());
        $offeringMeta = $this->loadOfferingMetadata($rows);
        $creatorNames = $this->loadCreatorNames($rows);
        $programTitles = $this->loadProgramTitles($rows);

        $items = $rows
            ->map(fn (Quiz $quiz) => $this->quizRow($quiz, $offeringMeta, $creatorNames, $programTitles))
            ->values();

        return response()->json([
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function creators(Request $request, string $scope): JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $base = $this->scopeQuizQuery($scope);
        if ($scope === 'faculty') {
            $base->where('created_by_user_id', $request->user()->id);
        }

        $creatorIds = $base->whereNotNull('created_by_user_id')->pluck('created_by_user_id')->unique()->values();
        if ($creatorIds->isEmpty()) {
            return response()->json(['items' => []]);
        }

        $rows = User::query()
            ->whereIn('id', $creatorIds)
            ->get(['id', 'name', 'email'])
            ->map(function (User $user) {
                $name = trim((string) $user->name);
                if ($name === '') {
                    $name = trim((string) $user->email);
                }

                return [
                    'id' => (int) $user->id,
                    'name' => $name !== '' ? $name : 'Portal User',
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['items' => $rows]);
    }

    public function offeringsCatalog(Request $request, string $scope): JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'program_id' => ['nullable', 'integer', 'min:1'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:8'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:4'],
            'period_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = DB::table('class_offerings as o')
            ->join('courses as c', 'c.id', '=', 'o.course_id')
            ->leftJoin('schedule_sections as ss', 'ss.id', '=', 'o.section_id')
            ->leftJoin('enrollment_periods as ep', 'ep.id', '=', 'o.period_id')
            ->leftJoin('program_catalogs as pc', DB::raw('LOWER(pc.title)'), '=', DB::raw('LOWER(ss.program_name)'));

        if ($scope === 'faculty') {
            $teacherId = $this->facultyTeacherId($request->user());
            if (! $teacherId) {
                return response()->json([
                    'items' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => (int) ($validated['per_page'] ?? 50),
                        'total' => 0,
                    ],
                ]);
            }
            $query->where('o.teacher_id', $teacherId);
        }

        $q = trim((string) ($validated['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($nested) use ($q) {
                $nested
                    ->where('c.code', 'like', '%' . $q . '%')
                    ->orWhere('c.title', 'like', '%' . $q . '%')
                    ->orWhere('ss.section_code', 'like', '%' . $q . '%')
                    ->orWhere('ss.program_name', 'like', '%' . $q . '%');
            });
        }

        if (! empty($validated['program_id'])) {
            $query->where('pc.id', (int) $validated['program_id']);
        }
        if (! empty($validated['year_level'])) {
            $query->where('c.year_level', (int) $validated['year_level']);
        }
        if (! empty($validated['semester'])) {
            $query->where('c.semester', (int) $validated['semester']);
        }
        if (! empty($validated['period_id'])) {
            $query->where('o.period_id', (int) $validated['period_id']);
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $page = (int) ($validated['page'] ?? 1);

        $paginator = $query
            ->select([
                'o.id as offering_id',
                'c.code as course_code',
                'c.title as course_title',
                'ss.section_code',
                'pc.id as program_id',
                DB::raw("COALESCE(pc.title, ss.program_name, 'Unassigned Program') as program_name"),
                'c.year_level',
                'c.semester',
                'ep.id as period_id',
                'ep.name as period_name',
            ])
            ->orderBy('c.code')
            ->orderBy('ss.section_code')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function ($row) {
            $code = trim((string) ($row->course_code ?? ''));
            $title = trim((string) ($row->course_title ?? 'Subject'));
            $section = trim((string) ($row->section_code ?? ''));
            $label = trim(($code !== '' ? $code . ' - ' : '') . $title . ($section !== '' ? ' (' . $section . ')' : ''));

            return [
                'id' => (int) $row->offering_id,
                'offering_id' => (int) $row->offering_id,
                'label' => $label !== '' ? $label : ('Offering #' . (int) $row->offering_id),
                'course_code' => $code,
                'course_title' => $title,
                'section_code' => $section,
                'program_id' => $row->program_id === null ? null : (int) $row->program_id,
                'program_name' => (string) ($row->program_name ?? 'Unassigned Program'),
                'year_level' => $row->year_level === null ? null : (int) $row->year_level,
                'semester' => $row->semester === null ? null : (int) $row->semester,
                'period_id' => $row->period_id === null ? null : (int) $row->period_id,
                'period_name' => (string) ($row->period_name ?? ''),
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function entranceCourses(Request $request, string $scope): JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $rows = ProgramCatalog::query()
            ->where('is_active', 1)
            ->where('type', 'diploma')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'title']);

        $items = $rows->map(function (ProgramCatalog $row) {
            $title = trim((string) $row->title);
            $code = Str::upper(Str::of($title)->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-')->substr(0, 24)->value());
            if ($code === '') {
                $code = 'COURSE-' . (int) $row->id;
            }

            return [
                'id' => (int) $row->id,
                'code' => $code,
                'label' => $title,
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function show(Request $request, int $id, string $scope): JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $quiz = $this->findQuizForScope($scope, $id);
        if (! $quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        if ($scope === 'faculty' && (int) $quiz->created_by_user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $rows = collect([$quiz]);
        $offeringMeta = $this->loadOfferingMetadata($rows);
        $creatorNames = $this->loadCreatorNames($rows);
        $programTitles = $this->loadProgramTitles($rows);

        return response()->json([
            'quiz' => $this->quizRow($quiz, $offeringMeta, $creatorNames, $programTitles),
        ]);
    }

    public function store(Request $request, string $scope): JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'pass_rate' => ['required', 'integer', 'min:1', 'max:100'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'quiz_type' => ['required', Rule::in(['regular', 'entrance'])],
            'offering_id' => ['nullable', 'integer', 'min:1', 'exists:class_offerings,id'],
            'course_program_id' => ['nullable', 'integer', 'min:1', 'exists:program_catalogs,id'],
            'shuffle_items' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ]);

        $isPublished = $validated['status'] === 'published';
        $publishedAt = $isPublished ? now() : null;
        $expiresAt = $isPublished ? now()->copy()->addMinutes((int) $validated['duration_minutes']) : null;

        $quiz = Quiz::query()->create([
            'scope' => $scope,
            'title' => trim((string) $validated['title']),
            'instructions' => trim((string) ($validated['instructions'] ?? '')),
            'pass_rate' => (int) $validated['pass_rate'],
            'duration_minutes' => (int) $validated['duration_minutes'],
            'status' => $validated['status'],
            'quiz_type' => $validated['quiz_type'],
            'created_by_user_id' => $request->user()->id,
            'offering_id' => $validated['offering_id'] ?? null,
            'course_program_id' => $validated['course_program_id'] ?? null,
            'shuffle_items' => array_key_exists('shuffle_items', $validated) ? (bool) $validated['shuffle_items'] : true,
            'shuffle_choices' => array_key_exists('shuffle_choices', $validated) ? (bool) $validated['shuffle_choices'] : true,
            'published_at' => $publishedAt,
            'expires_at' => $expiresAt,
            'share_token' => $isPublished ? $this->generateShareToken() : null,
            'invited_admission_ids' => [],
            'invited_recipient_emails' => [],
        ]);

        $rows = collect([$quiz]);
        $offeringMeta = $this->loadOfferingMetadata($rows);
        $creatorNames = $this->loadCreatorNames($rows);
        $programTitles = $this->loadProgramTitles($rows);

        return response()->json([
            'message' => 'Quiz created.',
            'quiz' => $this->quizRow($quiz, $offeringMeta, $creatorNames, $programTitles),
        ], 201);
    }

    public function update(Request $request, int $id, string $scope): JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $quiz = $this->findQuizForScope($scope, $id);
        if (! $quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        if ($scope === 'faculty' && (int) $quiz->created_by_user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'pass_rate' => ['required', 'integer', 'min:1', 'max:100'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'quiz_type' => ['required', Rule::in(['regular', 'entrance'])],
            'offering_id' => ['nullable', 'integer', 'min:1', 'exists:class_offerings,id'],
            'course_program_id' => ['nullable', 'integer', 'min:1', 'exists:program_catalogs,id'],
            'shuffle_items' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ]);

        $newStatus = (string) $validated['status'];
        $nextPublishedAt = $quiz->published_at;
        $nextShareToken = $quiz->share_token;

        if ($newStatus === 'published') {
            if (! $nextPublishedAt) {
                $nextPublishedAt = now();
            }
            if (! $nextShareToken) {
                $nextShareToken = $this->generateShareToken();
            }
        } else {
            $nextPublishedAt = null;
            $nextShareToken = null;
        }

        $nextExpiresAt = $nextPublishedAt
            ? Carbon::parse($nextPublishedAt)->copy()->addMinutes((int) $validated['duration_minutes'])
            : null;

        $quiz->update([
            'title' => trim((string) $validated['title']),
            'instructions' => trim((string) ($validated['instructions'] ?? '')),
            'pass_rate' => (int) $validated['pass_rate'],
            'duration_minutes' => (int) $validated['duration_minutes'],
            'status' => $newStatus,
            'quiz_type' => $validated['quiz_type'],
            'offering_id' => $validated['offering_id'] ?? null,
            'course_program_id' => $validated['course_program_id'] ?? null,
            'shuffle_items' => array_key_exists('shuffle_items', $validated) ? (bool) $validated['shuffle_items'] : (bool) $quiz->shuffle_items,
            'shuffle_choices' => array_key_exists('shuffle_choices', $validated) ? (bool) $validated['shuffle_choices'] : (bool) $quiz->shuffle_choices,
            'published_at' => $nextPublishedAt,
            'expires_at' => $nextExpiresAt,
            'share_token' => $nextShareToken,
        ]);

        $quiz->refresh();

        $rows = collect([$quiz]);
        $offeringMeta = $this->loadOfferingMetadata($rows);
        $creatorNames = $this->loadCreatorNames($rows);
        $programTitles = $this->loadProgramTitles($rows);

        return response()->json([
            'message' => 'Quiz updated.',
            'quiz' => $this->quizRow($quiz, $offeringMeta, $creatorNames, $programTitles),
        ]);
    }

    public function destroy(Request $request, int $id, string $scope): JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $quiz = $this->findQuizForScope($scope, $id);
        if (! $quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        if ($scope === 'faculty' && (int) $quiz->created_by_user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $quiz->delete();

        return response()->json(['message' => 'Quiz deleted.']);
    }

    public function publish(Request $request, int $id, string $scope): JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $quiz = $this->findQuizForScope($scope, $id);
        if (! $quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        if ($scope === 'faculty' && (int) $quiz->created_by_user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $validated = $request->validate([
            'send_email' => ['nullable', 'boolean'],
            'admission_ids' => ['nullable', 'array'],
            'admission_ids.*' => ['required_with:admission_ids', 'integer', 'min:1'],
            'recipient_emails' => ['nullable', 'array'],
            'recipient_emails.*' => ['required_with:recipient_emails', 'email:rfc,dns', 'max:190'],
        ]);

        $publishedAt = now();
        $expiresAt = now()->copy()->addMinutes((int) $quiz->duration_minutes);
        $shareToken = $quiz->share_token ?: $this->generateShareToken();
        $admissionIds = array_values(array_unique(array_map('intval', $validated['admission_ids'] ?? [])));
        $recipientEmails = array_values(array_unique(array_map(fn ($email) => Str::lower(trim((string) $email)), $validated['recipient_emails'] ?? [])));

        $quiz->update([
            'status' => 'published',
            'published_at' => $publishedAt,
            'expires_at' => $expiresAt,
            'share_token' => $shareToken,
            'invited_admission_ids' => $quiz->quiz_type === 'entrance' ? $admissionIds : ($quiz->invited_admission_ids ?? []),
            'invited_recipient_emails' => $quiz->quiz_type === 'entrance' ? $recipientEmails : ($quiz->invited_recipient_emails ?? []),
        ]);

        $quiz->refresh();

        $rows = collect([$quiz]);
        $offeringMeta = $this->loadOfferingMetadata($rows);
        $creatorNames = $this->loadCreatorNames($rows);
        $programTitles = $this->loadProgramTitles($rows);

        return response()->json([
            'message' => 'Quiz published.',
            'quiz' => $this->quizRow($quiz, $offeringMeta, $creatorNames, $programTitles),
            'link_url' => $this->buildShareLink((string) $quiz->share_token),
            'share_token' => $quiz->share_token,
            'expires_at' => $quiz->expires_at?->toISOString(),
        ]);
    }

    private function verifyQuizWriteAccess(Request $request, string $scope, int $quizId): array|JsonResponse
    {
        if ($resp = $this->assertScopeRole($request, $scope)) {
            return $resp;
        }

        $quiz = $this->findQuizForScope($scope, $quizId);
        if (! $quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        if ($scope === 'faculty' && (int) $quiz->created_by_user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        return ['quiz' => $quiz];
    }

    public function listItems(Request $request, int $quizId, string $scope): JsonResponse
    {
        $guard = $this->verifyQuizWriteAccess($request, $scope, $quizId);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $items = QuizItem::query()
            ->where('quiz_id', $quizId)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(function (QuizItem $item) {
                return [
                    'id' => (int) $item->id,
                    'quiz_id' => (int) $item->quiz_id,
                    'prompt' => (string) $item->prompt,
                    'choices' => array_values($item->choices ?? []),
                    'correct_choice_id' => (string) $item->correct_choice_id,
                    'order' => (int) $item->display_order,
                    'created_at' => $item->created_at?->toISOString(),
                    'updated_at' => $item->updated_at?->toISOString(),
                ];
            })
            ->values();

        return response()->json(['items' => $items]);
    }

    private function quizItemValidationRules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:2000'],
            'choices' => ['required', 'array', 'min:2', 'max:8'],
            'choices.*.id' => ['nullable', 'string', 'max:20'],
            'choices.*.text' => ['required', 'string', 'max:500'],
            'correct_choice_id' => ['required', 'string', 'max:20'],
            'order' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function storeItem(Request $request, int $quizId, string $scope): JsonResponse
    {
        $guard = $this->verifyQuizWriteAccess($request, $scope, $quizId);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $validated = $request->validate($this->quizItemValidationRules());

        $choices = $this->normalizeChoices($validated['choices']);
        $correctChoiceId = $this->normalizeChoiceId((string) $validated['correct_choice_id']);
        if (! collect($choices)->contains(fn ($row) => $row['id'] === $correctChoiceId)) {
            return response()->json(['message' => 'Correct choice id must exist in the choices array.'], 422);
        }

        $maxOrder = (int) QuizItem::query()->where('quiz_id', $quizId)->max('display_order');

        $item = QuizItem::query()->create([
            'quiz_id' => $quizId,
            'prompt' => trim((string) $validated['prompt']),
            'choices' => $choices,
            'correct_choice_id' => $correctChoiceId,
            'display_order' => (int) ($validated['order'] ?? ($maxOrder + 1)),
        ]);

        return response()->json([
            'message' => 'Quiz item created.',
            'item' => [
                'id' => (int) $item->id,
                'quiz_id' => (int) $item->quiz_id,
                'prompt' => (string) $item->prompt,
                'choices' => array_values($item->choices ?? []),
                'correct_choice_id' => (string) $item->correct_choice_id,
                'order' => (int) $item->display_order,
                'created_at' => $item->created_at?->toISOString(),
                'updated_at' => $item->updated_at?->toISOString(),
            ],
        ], 201);
    }

    public function updateItem(Request $request, int $quizId, int $itemId, string $scope): JsonResponse
    {
        $guard = $this->verifyQuizWriteAccess($request, $scope, $quizId);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $item = QuizItem::query()->where('quiz_id', $quizId)->where('id', $itemId)->first();
        if (! $item) {
            return response()->json(['message' => 'Quiz item not found.'], 404);
        }

        $validated = $request->validate($this->quizItemValidationRules());

        $choices = $this->normalizeChoices($validated['choices']);
        $correctChoiceId = $this->normalizeChoiceId((string) $validated['correct_choice_id']);
        if (! collect($choices)->contains(fn ($row) => $row['id'] === $correctChoiceId)) {
            return response()->json(['message' => 'Correct choice id must exist in the choices array.'], 422);
        }

        $item->update([
            'prompt' => trim((string) $validated['prompt']),
            'choices' => $choices,
            'correct_choice_id' => $correctChoiceId,
            'display_order' => (int) ($validated['order'] ?? $item->display_order),
        ]);

        $item->refresh();

        return response()->json([
            'message' => 'Quiz item updated.',
            'item' => [
                'id' => (int) $item->id,
                'quiz_id' => (int) $item->quiz_id,
                'prompt' => (string) $item->prompt,
                'choices' => array_values($item->choices ?? []),
                'correct_choice_id' => (string) $item->correct_choice_id,
                'order' => (int) $item->display_order,
                'created_at' => $item->created_at?->toISOString(),
                'updated_at' => $item->updated_at?->toISOString(),
            ],
        ]);
    }

    public function destroyItem(Request $request, int $quizId, int $itemId, string $scope): JsonResponse
    {
        $guard = $this->verifyQuizWriteAccess($request, $scope, $quizId);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $item = QuizItem::query()->where('quiz_id', $quizId)->where('id', $itemId)->first();
        if (! $item) {
            return response()->json(['message' => 'Quiz item not found.'], 404);
        }

        $item->delete();
        return response()->json(['message' => 'Quiz item deleted.']);
    }

    private function attemptSummaryRow(QuizAttempt $attempt): array
    {
        return [
            'attempt_id' => (int) $attempt->id,
            'quiz_id' => (int) $attempt->quiz_id,
            'student_name' => (string) ($attempt->student_name ?? 'Student'),
            'student_email' => $attempt->student_email ? (string) $attempt->student_email : null,
            'score' => (int) ($attempt->score ?? 0),
            'total' => (int) ($attempt->total ?? 0),
            'correct_count' => (int) ($attempt->correct_count ?? 0),
            'wrong_count' => (int) ($attempt->wrong_count ?? 0),
            'passed' => (bool) ($attempt->passed ?? false),
            'started_at' => $attempt->started_at?->toISOString(),
            'submitted_at' => $attempt->submitted_at?->toISOString(),
            'auto_submitted' => (bool) $attempt->auto_submitted,
        ];
    }

    public function listResults(Request $request, int $quizId, string $scope): JsonResponse
    {
        $guard = $this->verifyQuizWriteAccess($request, $scope, $quizId);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['passed', 'failed'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = QuizAttempt::query()
            ->where('quiz_id', $quizId)
            ->whereNotNull('submitted_at');

        $q = trim((string) ($validated['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($nested) use ($q) {
                $nested
                    ->where('student_name', 'like', '%' . $q . '%')
                    ->orWhere('student_email', 'like', '%' . $q . '%');
            });
        }

        if (! empty($validated['status'])) {
            $query->where('passed', $validated['status'] === 'passed');
        }
        if (! empty($validated['date_from'])) {
            $query->whereDate('submitted_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('submitted_at', '<=', $validated['date_to']);
        }

        $items = $query
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (QuizAttempt $attempt) => $this->attemptSummaryRow($attempt))
            ->values();

        return response()->json(['items' => $items]);
    }

    public function resultDetail(Request $request, int $quizId, int $attemptId, string $scope): JsonResponse
    {
        $guard = $this->verifyQuizWriteAccess($request, $scope, $quizId);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $attempt = QuizAttempt::query()
            ->where('quiz_id', $quizId)
            ->where('id', $attemptId)
            ->first();

        if (! $attempt) {
            return response()->json(['message' => 'Attempt not found.'], 404);
        }

        $items = QuizAttemptAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->join('quiz_items as qi', 'qi.id', '=', 'quiz_attempt_answers.quiz_item_id')
            ->select([
                'qi.id as item_id',
                'qi.prompt',
                'quiz_attempt_answers.selected_choice_id',
                'quiz_attempt_answers.selected_choice_text',
                'quiz_attempt_answers.correct_choice_id',
                'quiz_attempt_answers.correct_choice_text',
                'quiz_attempt_answers.is_correct',
            ])
            ->orderBy('qi.display_order')
            ->orderBy('qi.id')
            ->get()
            ->map(fn ($row) => [
                'item_id' => (int) $row->item_id,
                'prompt' => (string) ($row->prompt ?? 'Question'),
                'selected_choice_id' => $row->selected_choice_id ? (string) $row->selected_choice_id : null,
                'selected_choice_text' => $row->selected_choice_text ? (string) $row->selected_choice_text : null,
                'correct_choice_id' => (string) $row->correct_choice_id,
                'correct_choice_text' => $row->correct_choice_text ? (string) $row->correct_choice_text : null,
                'is_correct' => (bool) $row->is_correct,
            ])
            ->values();

        return response()->json([
            'attempt' => $this->attemptSummaryRow($attempt),
            'items' => $items,
        ]);
    }

    private function snapshotFromItems(Quiz $quiz, Collection $items): array
    {
        $rows = $items
            ->sortBy('display_order')
            ->values()
            ->map(function (QuizItem $item) {
                $choices = array_values($item->choices ?? []);
                return [
                    'id' => (int) $item->id,
                    'prompt' => (string) $item->prompt,
                    'choices' => $choices,
                    'correct_choice_id' => $this->normalizeChoiceId((string) $item->correct_choice_id),
                ];
            })
            ->values()
            ->all();

        if ($quiz->shuffle_items) {
            shuffle($rows);
        }

        if ($quiz->shuffle_choices) {
            foreach ($rows as &$row) {
                if (count($row['choices']) > 1) {
                    shuffle($row['choices']);
                }
            }
            unset($row);
        }

        return $rows;
    }

    private function snapshotForResponse(array $snapshot): array
    {
        return array_map(function (array $item) {
            return [
                'id' => (int) ($item['id'] ?? 0),
                'prompt' => (string) ($item['prompt'] ?? 'Question'),
                'points' => 1,
                'choices' => array_values(array_map(function ($choice, $index) {
                    $value = (array) $choice;
                    return [
                        'id' => $this->normalizeChoiceId((string) ($value['id'] ?? ''), (int) $index),
                        'text' => trim((string) ($value['text'] ?? ('Choice ' . ($index + 1)))),
                    ];
                }, $item['choices'] ?? [], array_keys($item['choices'] ?? []))),
            ];
        }, $snapshot);
    }

    private function gradeSnapshot(array $snapshot, array $answers, int $passRate): array
    {
        $total = count($snapshot);
        $correct = 0;
        $rows = [];

        foreach ($snapshot as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $selected = $this->normalizeChoiceId((string) ($answers[(string) $itemId] ?? ''));
            $correctChoice = $this->normalizeChoiceId((string) ($item['correct_choice_id'] ?? 'A'));
            $choiceMap = collect($item['choices'] ?? [])->mapWithKeys(function ($choice) {
                $value = (array) $choice;
                return [$this->normalizeChoiceId((string) ($value['id'] ?? '')) => trim((string) ($value['text'] ?? ''))];
            });

            $isCorrect = $selected !== '' && $selected === $correctChoice;
            if ($isCorrect) {
                $correct += 1;
            }

            $rows[] = [
                'quiz_item_id' => $itemId,
                'selected_choice_id' => $selected !== '' ? $selected : null,
                'selected_choice_text' => $selected !== '' ? ($choiceMap[$selected] ?? null) : null,
                'correct_choice_id' => $correctChoice,
                'correct_choice_text' => $choiceMap[$correctChoice] ?? null,
                'is_correct' => $isCorrect,
            ];
        }

        $wrong = max(0, $total - $correct);
        $passed = $total > 0 ? (($correct / $total) * 100) >= $passRate : false;

        return [
            'score' => $correct,
            'total' => $total,
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'passed' => $passed,
            'rows' => $rows,
        ];
    }

    public function preview(Request $request, int $quizId, string $scope): JsonResponse
    {
        $guard = $this->verifyQuizWriteAccess($request, $scope, $quizId);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        /** @var Quiz $quiz */
        $quiz = $guard['quiz'];

        $items = QuizItem::query()->where('quiz_id', $quizId)->orderBy('display_order')->orderBy('id')->get();
        $snapshot = $this->snapshotFromItems($quiz, $items);

        return response()->json([
            'quiz' => [
                'id' => (int) $quiz->id,
                'title' => (string) $quiz->title,
                'instructions' => (string) ($quiz->instructions ?? ''),
                'duration_minutes' => (int) $quiz->duration_minutes,
                'pass_rate' => (int) $quiz->pass_rate,
                'shuffle_items' => (bool) $quiz->shuffle_items,
                'shuffle_choices' => (bool) $quiz->shuffle_choices,
                'published_at' => $quiz->published_at?->toISOString(),
                'expires_at' => $quiz->expires_at?->toISOString(),
            ],
            'questions' => $this->snapshotForResponse($snapshot),
        ]);
    }

    public function previewSubmit(Request $request, int $quizId, string $scope): JsonResponse
    {
        $guard = $this->verifyQuizWriteAccess($request, $scope, $quizId);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        /** @var Quiz $quiz */
        $quiz = $guard['quiz'];

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'auto_submitted' => ['nullable', 'boolean'],
        ]);

        $items = QuizItem::query()->where('quiz_id', $quizId)->orderBy('display_order')->orderBy('id')->get();
        $snapshot = $this->snapshotFromItems($quiz, $items);
        $graded = $this->gradeSnapshot($snapshot, $validated['answers'], (int) $quiz->pass_rate);

        return response()->json([
            'code' => ! empty($validated['auto_submitted']) ? 'ATTEMPT_TIMEOUT' : null,
            'message' => ! empty($validated['auto_submitted']) ? 'Preview auto-submitted.' : 'Preview submitted.',
            'score' => $graded['score'],
            'total' => $graded['total'],
            'correct_count' => $graded['correct_count'],
            'wrong_count' => $graded['wrong_count'],
            'passed' => $graded['passed'],
        ]);
    }

    private function resolveStudentAdmission(User $user): ?AdmissionApplication
    {
        $email = trim((string) $user->email);

        $query = AdmissionApplication::query()
            ->where(function ($nested) use ($user, $email) {
                $nested->where('created_user_id', $user->id);
                if ($email !== '') {
                    $nested->orWhereRaw('LOWER(email) = ?', [Str::lower($email)]);
                }
            })
            ->orderByRaw('CASE WHEN approved_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('approved_at')
            ->orderByDesc('id');

        return $query->first();
    }

    private function entranceAuthorizedForStudent(Quiz $quiz, User $user, ?AdmissionApplication $application): bool
    {
        if ($quiz->quiz_type !== 'entrance') {
            return true;
        }

        $invitedIds = collect($quiz->invited_admission_ids ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $invitedEmails = collect($quiz->invited_recipient_emails ?? [])->map(fn ($email) => Str::lower(trim((string) $email)))->filter()->unique()->values();

        if ($invitedIds->isEmpty() && $invitedEmails->isEmpty()) {
            return true;
        }

        $userEmail = Str::lower(trim((string) ($user->email ?? '')));
        if ($userEmail !== '' && $invitedEmails->contains($userEmail)) {
            return true;
        }

        if ($application && $invitedIds->contains((int) $application->id)) {
            return true;
        }

        return false;
    }

    private function loadQuizByShareToken(string $token): ?Quiz
    {
        return Quiz::query()->where('share_token', $token)->where('status', 'published')->first();
    }

    private function unavailableLinkResponse(string $message = 'This quiz link is not available.', ?string $code = 'QUIZ_UNAVAILABLE'): JsonResponse
    {
        return response()->json([
            'status' => 'unavailable',
            'code' => $code,
            'message' => $message,
            'quiz' => null,
            'questions' => [],
            'attempt' => null,
        ]);
    }

    public function validateLink(Request $request, string $token): JsonResponse
    {
        if ($resp = $this->assertStudentRole($request)) {
            return $resp;
        }

        $quiz = $this->loadQuizByShareToken($token);
        if (! $quiz) {
            return $this->unavailableLinkResponse();
        }

        if (! $quiz->expires_at || now()->greaterThanOrEqualTo($quiz->expires_at)) {
            return response()->json([
                'status' => 'expired',
                'code' => 'LINK_EXPIRED',
                'message' => 'This quiz link has expired.',
                'quiz' => null,
                'questions' => [],
                'attempt' => null,
            ]);
        }

        $application = $this->resolveStudentAdmission($request->user());
        if (! $this->entranceAuthorizedForStudent($quiz, $request->user(), $application)) {
            return $this->unavailableLinkResponse('This entrance quiz is restricted to invited incoming students using existing student credentials.');
        }

        $activeAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('student_user_id', $request->user()->id)
            ->whereNull('submitted_at')
            ->orderByDesc('id')
            ->first();

        $questions = [];
        if ($activeAttempt) {
            $snapshot = (array) ($activeAttempt->context['snapshot'] ?? []);
            $questions = $this->snapshotForResponse($snapshot);
        }

        return response()->json([
            'status' => 'active',
            'code' => null,
            'message' => 'Quiz is available.',
            'quiz' => [
                'id' => (int) $quiz->id,
                'title' => (string) $quiz->title,
                'instructions' => (string) ($quiz->instructions ?? ''),
                'duration_minutes' => (int) $quiz->duration_minutes,
                'pass_rate' => (int) $quiz->pass_rate,
                'shuffle_items' => (bool) $quiz->shuffle_items,
                'shuffle_choices' => (bool) $quiz->shuffle_choices,
                'published_at' => $quiz->published_at?->toISOString(),
                'expires_at' => $quiz->expires_at?->toISOString(),
            ],
            'questions' => $questions,
            'attempt' => $activeAttempt
                ? [
                    'id' => (int) $activeAttempt->id,
                    'ends_at' => $activeAttempt->ends_at?->toISOString(),
                ]
                : null,
        ]);
    }

    public function startAttempt(Request $request, string $token): JsonResponse
    {
        if ($resp = $this->assertStudentRole($request)) {
            return $resp;
        }

        $quiz = $this->loadQuizByShareToken($token);
        if (! $quiz) {
            return response()->json(['message' => 'Quiz link is unavailable.'], 404);
        }

        if (! $quiz->expires_at || now()->greaterThanOrEqualTo($quiz->expires_at)) {
            return response()->json(['message' => 'LINK_EXPIRED'], 422);
        }

        $application = $this->resolveStudentAdmission($request->user());
        if (! $this->entranceAuthorizedForStudent($quiz, $request->user(), $application)) {
            return response()->json(['message' => 'This entrance quiz is restricted to invited incoming students using existing student credentials.'], 403);
        }

        $existingSubmitted = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('student_user_id', $request->user()->id)
            ->whereNotNull('submitted_at')
            ->exists();

        if ($existingSubmitted) {
            return response()->json(['message' => 'Attempt already submitted.'], 422);
        }

        $activeAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('student_user_id', $request->user()->id)
            ->whereNull('submitted_at')
            ->orderByDesc('id')
            ->first();

        if ($activeAttempt) {
            $snapshot = (array) ($activeAttempt->context['snapshot'] ?? []);
            return response()->json([
                'attempt_id' => (int) $activeAttempt->id,
                'ends_at' => $activeAttempt->ends_at?->toISOString(),
                'questions' => $this->snapshotForResponse($snapshot),
            ]);
        }

        $items = QuizItem::query()->where('quiz_id', $quiz->id)->orderBy('display_order')->orderBy('id')->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'Quiz has no items yet.'], 422);
        }

        $snapshot = $this->snapshotFromItems($quiz, $items);

        $candidateEnd = now()->copy()->addMinutes((int) $quiz->duration_minutes);
        $attemptEnd = $quiz->expires_at && $candidateEnd->greaterThan($quiz->expires_at)
            ? $quiz->expires_at->copy()
            : $candidateEnd;

        $attempt = QuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'student_user_id' => $request->user()->id,
            'student_admission_id' => $application?->id,
            'student_name' => trim((string) ($request->user()->name ?: 'Student')),
            'student_email' => trim((string) ($request->user()->email ?: '')) ?: null,
            'started_at' => now(),
            'ends_at' => $attemptEnd,
            'context' => [
                'token' => $token,
                'snapshot' => $snapshot,
            ],
        ]);

        return response()->json([
            'attempt_id' => (int) $attempt->id,
            'ends_at' => $attempt->ends_at?->toISOString(),
            'questions' => $this->snapshotForResponse($snapshot),
        ]);
    }

    public function submitAttempt(Request $request, int $attemptId): JsonResponse
    {
        if ($resp = $this->assertStudentRole($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'auto_submitted' => ['nullable', 'boolean'],
        ]);

        $attempt = QuizAttempt::query()
            ->where('id', $attemptId)
            ->where('student_user_id', $request->user()->id)
            ->first();

        if (! $attempt) {
            return response()->json(['message' => 'Attempt not found.'], 404);
        }

        if ($attempt->submitted_at) {
            return response()->json(['message' => 'Attempt already submitted.'], 422);
        }

        $autoSubmitted = ! empty($validated['auto_submitted']);
        if (! $autoSubmitted && now()->greaterThan($attempt->ends_at)) {
            return response()->json([
                'code' => 'ATTEMPT_TIMEOUT',
                'message' => 'Attempt timed out before submission.',
                'score' => null,
                'total' => null,
                'passed' => null,
            ], 422);
        }

        $snapshot = (array) ($attempt->context['snapshot'] ?? []);
        if (empty($snapshot)) {
            return response()->json(['message' => 'Attempt snapshot is unavailable.'], 422);
        }

        $quiz = Quiz::query()->find($attempt->quiz_id);
        if (! $quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $graded = $this->gradeSnapshot($snapshot, $validated['answers'], (int) $quiz->pass_rate);

        DB::transaction(function () use ($attempt, $graded, $autoSubmitted) {
            QuizAttemptAnswer::query()->where('attempt_id', $attempt->id)->delete();
            foreach ($graded['rows'] as $row) {
                QuizAttemptAnswer::query()->create([
                    'attempt_id' => $attempt->id,
                    'quiz_item_id' => (int) $row['quiz_item_id'],
                    'selected_choice_id' => $row['selected_choice_id'],
                    'selected_choice_text' => $row['selected_choice_text'],
                    'correct_choice_id' => (string) $row['correct_choice_id'],
                    'correct_choice_text' => $row['correct_choice_text'],
                    'is_correct' => (bool) $row['is_correct'],
                ]);
            }

            $attempt->update([
                'submitted_at' => now(),
                'auto_submitted' => $autoSubmitted,
                'score' => (int) $graded['score'],
                'total' => (int) $graded['total'],
                'correct_count' => (int) $graded['correct_count'],
                'wrong_count' => (int) $graded['wrong_count'],
                'passed' => (bool) $graded['passed'],
            ]);
        });

        return response()->json([
            'code' => $autoSubmitted ? 'ATTEMPT_TIMEOUT' : null,
            'message' => $autoSubmitted ? 'Time is up. Quiz auto-submitted.' : 'Quiz submitted.',
            'score' => (int) $graded['score'],
            'total' => (int) $graded['total'],
            'correct_count' => (int) $graded['correct_count'],
            'wrong_count' => (int) $graded['wrong_count'],
            'passed' => (bool) $graded['passed'],
        ]);
    }
}
