<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class EnrollmentPeriodRollover
{
    public static function rolloverToNextPeriod(?int $fromPeriodId = null, bool $activateNext = false): array
    {
        $active = $fromPeriodId
            ? DB::table('enrollment_periods')->where('id', $fromPeriodId)->first()
            : DB::table('enrollment_periods')->where('is_active', 1)->orderByDesc('id')->first();

        if (! $active) {
            $active = DB::table('enrollment_periods')->orderByDesc('id')->first();
        }

        if (! $active) {
            throw new \RuntimeException('No enrollment period found.');
        }

        [$term, $startYear, $endYear] = self::parsePeriodName((string) $active->name);

        if (! $term) {
            throw new \RuntimeException('Unable to parse period name. Use format like "1st Semester AY 2026-2027".');
        }

        [$nextTerm, $nextStart, $nextEnd] = self::nextTermAndYear($term, $startYear, $endYear);

        DB::transaction(function () use ($nextTerm, $nextStart, $nextEnd, $activateNext, &$nextPeriod) {
            self::ensureAcademicYearPeriods($nextStart, $nextEnd);
            $nextName = self::formatPeriodName($nextTerm, $nextStart, $nextEnd);
            $nextPeriod = DB::table('enrollment_periods')->where('name', $nextName)->first();

            if (! $nextPeriod) {
                throw new \RuntimeException('Failed to resolve next enrollment period.');
            }

            if ($activateNext) {
                DB::table('enrollment_periods')->update(['is_active' => 0, 'updated_at' => now()]);
                DB::table('enrollment_periods')->where('id', $nextPeriod->id)->update(['is_active' => 1, 'updated_at' => now()]);
            }
        });

        return [
            'from' => [
                'id' => (int) $active->id,
                'name' => (string) $active->name,
            ],
            'to' => [
                'id' => (int) $nextPeriod->id,
                'name' => (string) $nextPeriod->name,
            ],
            'activated' => $activateNext,
        ];
    }

    public static function ensureAcademicYearPeriods(int $startYear, int $endYear): void
    {
        $terms = ['1st Semester', '2nd Semester', 'Summer'];

        foreach ($terms as $term) {
            $name = self::formatPeriodName($term, $startYear, $endYear);
            $exists = DB::table('enrollment_periods')->where('name', $name)->exists();

            if (! $exists) {
                DB::table('enrollment_periods')->insert([
                    'name' => $name,
                    'is_active' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private static function nextTermAndYear(string $term, int $startYear, int $endYear): array
    {
        if ($term === '1st Semester') {
            return ['2nd Semester', $startYear, $endYear];
        }

        if ($term === '2nd Semester') {
            return ['Summer', $startYear, $endYear];
        }

        return ['1st Semester', $startYear + 1, $endYear + 1];
    }

    private static function formatPeriodName(string $term, int $startYear, int $endYear): string
    {
        return sprintf('%s AY %d-%d', $term, $startYear, $endYear);
    }

    private static function parsePeriodName(string $name): array
    {
        $pattern = '/^(1st Semester|2nd Semester|Summer)\s+AY\s+(\d{4})-(\d{4})$/i';
        if (! preg_match($pattern, trim($name), $matches)) {
            return [null, null, null];
        }

        $term = strtolower($matches[1]);
        $normalized = match ($term) {
            '1st semester' => '1st Semester',
            '2nd semester' => '2nd Semester',
            default => 'Summer',
        };

        return [$normalized, (int) $matches[2], (int) $matches[3]];
    }
}
