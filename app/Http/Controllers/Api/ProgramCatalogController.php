<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProgramCatalogController extends Controller
{
    private function resolveBadgeText(bool $isLimitedSlots, bool $isActive): string
    {
        if (! $isActive) {
            return 'Inactive';
        }

        return $isLimitedSlots ? 'Limited slots' : 'Active';
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

    public function publicIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['certificate', 'diploma'])],
        ]);

        $programs = ProgramCatalog::query()
            ->where('type', $validated['type'])
            ->orderByDesc('is_active')
            ->orderBy('title')
            ->get();

        return response()->json([
            'programs' => $programs,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['certificate', 'diploma'])],
        ]);

        $query = ProgramCatalog::query()
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderBy('title');

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        return response()->json([
            'programs' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['certificate', 'diploma'])],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'duration' => ['required', 'string', 'max:100'],
            'credential_label' => ['required', 'string', 'max:100'],
            'icon_key' => ['required', 'string', 'max:50'],
            'theme_key' => ['required', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_limited_slots' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($validated['title']);
        $uniqueSlug = $slug;
        $suffix = 2;
        while (ProgramCatalog::query()->where('slug', $uniqueSlug)->exists()) {
            $uniqueSlug = "{$slug}-{$suffix}";
            $suffix++;
        }

        $isActive = $validated['is_active'] ?? true;
        $isLimitedSlots = $validated['is_limited_slots'] ?? false;

        $program = ProgramCatalog::query()->create([
            ...$validated,
            'slug' => $uniqueSlug,
            'badge_text' => $this->resolveBadgeText($isLimitedSlots, $isActive),
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_limited_slots' => $isLimitedSlots,
            'is_active' => $isActive,
        ]);

        return response()->json([
            'message' => 'Program added successfully.',
            'program' => $program,
        ], 201);
    }

    public function update(Request $request, ProgramCatalog $program): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'type' => ['sometimes', Rule::in(['certificate', 'diploma'])],
            'title' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'duration' => ['sometimes', 'string', 'max:100'],
            'credential_label' => ['sometimes', 'string', 'max:100'],
            'icon_key' => ['sometimes', 'string', 'max:50'],
            'theme_key' => ['sometimes', 'string', 'max:50'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_limited_slots' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('title', $validated)) {
            $slug = Str::slug($validated['title']);
            $uniqueSlug = $slug;
            $suffix = 2;
            while (
                ProgramCatalog::query()
                    ->where('slug', $uniqueSlug)
                    ->where('id', '!=', $program->id)
                    ->exists()
            ) {
                $uniqueSlug = "{$slug}-{$suffix}";
                $suffix++;
            }
            $validated['slug'] = $uniqueSlug;
        }

        $isActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : (bool) $program->is_active;
        $isLimitedSlots = array_key_exists('is_limited_slots', $validated)
            ? (bool) $validated['is_limited_slots']
            : (bool) $program->is_limited_slots;
        $validated['badge_text'] = $this->resolveBadgeText($isLimitedSlots, $isActive);

        $program->fill($validated);
        $program->save();

        return response()->json([
            'message' => 'Program updated successfully.',
            'program' => $program->fresh(),
        ]);
    }

    public function destroy(Request $request, ProgramCatalog $program): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        return response()->json([
            'message' => 'Deletion is disabled. Set the program status to inactive instead.',
        ], 405);
    }
}
