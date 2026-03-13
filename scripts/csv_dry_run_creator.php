<?php
declare(strict_types=1);

const REQUIRED_HEADERS = [
    'first_name',
    'last_name',
    'email',
    'course',
    'year_level',
    'gender',
    'student_id',
];

const YEAR_SCOPES = [
    'all' => 'All years',
    '1' => '1st year',
    '2' => '2nd year',
    '3' => '3rd year',
    '4' => '4th year',
];

const SCENARIOS = [
    'valid_only' => 'Valid only',
    'invalid_only' => 'Invalid only',
    'mixed' => 'Mixed valid + invalid',
    'full_matrix' => 'Full invalid matrix',
];

const INVALID_CASES = [
    'missing_first_name',
    'missing_last_name',
    'missing_email',
    'missing_course',
    'missing_year_level',
    'missing_gender',
    'missing_student_id',
    'invalid_year_level',
    'invalid_gender',
    'unknown_course',
    'multi_error',
];

const FALLBACK_COURSES = [
    'Bachelor of Science in Information Technology',
    'Bachelor of Science in Computer Science',
    'Bachelor of Technical Vocational Teacher Education',
    'Bachelor of Elementary Education',
    'Bachelor of Science in Business Administration',
    'Bachelor of Science in Hospitality Management',
    '3-Year Diploma in ICT',
    'Housekeeping NCII',
    'Health Care Services NCII',
    'Forklift NCII',
];

final class CsvDryRunCreator
{
    private string $basePath;
    private string $outputRoot;
    private string $courseSource = 'fallback';
    private int $rowSeed = 0;
    /** @var string[] */
    private array $courses = [];
    /** @var string[] */
    private array $sessionArtifacts = [];
    /** @var string[] */
    private array $firstNames = [
        'Alyssa', 'Bianca', 'Carlo', 'Darren', 'Elijah', 'Faith', 'Gabriel', 'Hannah',
        'Isabel', 'Jared', 'Katrina', 'Lance', 'Mira', 'Noel', 'Olivia', 'Paolo',
        'Queenie', 'Rafael', 'Sophia', 'Tyler', 'Uma', 'Vince', 'Wendy', 'Xavier',
        'Yanna', 'Zane',
    ];
    /** @var string[] */
    private array $lastNames = [
        'Aguilar', 'Bautista', 'Castro', 'DelosSantos', 'Espino', 'Flores', 'Garcia', 'Hernandez',
        'Ibarra', 'Jimenez', 'Lopez', 'Mendoza', 'Navarro', 'Ortega', 'Perez', 'Quinto',
        'Reyes', 'Santos', 'Torres', 'Uy', 'Valdez', 'Wong', 'Yu', 'Zamora',
    ];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, "\\/");
        $this->outputRoot = $this->basePath . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'dry-run-csv';
    }

    public function run(): void
    {
        $this->printBanner();
        $this->courses = $this->loadCoursesDbFirst();
        $this->ensureDirectory($this->outputRoot);

        while (true) {
            echo PHP_EOL;
            echo "Main Menu" . PHP_EOL;
            echo "1. Generate single CSV" . PHP_EOL;
            echo "2. Generate scenario pack (all years + all scenarios)" . PHP_EOL;
            echo "3. List generated files" . PHP_EOL;
            echo "4. Show function guide" . PHP_EOL;
            echo "5. Exit" . PHP_EOL;

            $choice = $this->readLine('Choose option [1-5]: ');
            if ($choice === '1') {
                $this->generateSingleCsv();
                continue;
            }
            if ($choice === '2') {
                $this->generateScenarioPack();
                continue;
            }
            if ($choice === '3') {
                $this->listGeneratedFiles();
                continue;
            }
            if ($choice === '4') {
                $this->showFunctionGuide();
                continue;
            }
            if ($choice === '5') {
                echo "Done. Exiting CSV dry-run creator." . PHP_EOL;
                return;
            }

            echo "Invalid option. Enter a number from 1 to 5." . PHP_EOL;
        }
    }

    private function printBanner(): void
    {
        echo PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo " TCLASS CSV Dry-Run Generator (CLI) " . PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo "Output root: " . $this->outputRoot . PHP_EOL;
    }

    private function showFunctionGuide(): void
    {
        echo PHP_EOL;
        echo "Function Guide" . PHP_EOL;
        echo "- Generate single CSV: create one file by year scope + scenario + row count." . PHP_EOL;
        echo "- Generate scenario pack: create a full dry-run set for all scopes and scenarios." . PHP_EOL;
        echo "- List generated files: view newest CSV/manifest artifacts under tests/dry-run-csv." . PHP_EOL;
        echo "- CSV schema always uses required headers:" . PHP_EOL;
        echo "  " . implode(',', REQUIRED_HEADERS) . PHP_EOL;
        echo "- Course source is DB-first from program_catalogs; fallback list is used if DB query fails." . PHP_EOL;
    }

    private function generateSingleCsv(): void
    {
        $scope = $this->promptChoice("Select year scope:", YEAR_SCOPES, 'all');
        $scenario = $this->promptChoice("Select scenario:", SCENARIOS, 'mixed');
        $count = $this->promptInt('How many rows to generate? [default 50]: ', 50, 1, 100000);
        $mixedInvalidPercent = 30;

        if ($scenario === 'mixed') {
            $mixedInvalidPercent = $this->promptInt('Invalid row percentage for mixed mode? [default 30]: ', 30, 1, 99);
        }

        $dataset = $this->buildDataset($scope, $scenario, $count, $mixedInvalidPercent);
        $runDir = $this->makeRunDirectory('single');
        $scopeSlug = $this->scopeSlug($scope);
        $scenarioSlug = str_replace('_', '-', $scenario);
        $fileName = $scopeSlug . '__' . $scenarioSlug . '__' . count($dataset['rows']) . '.csv';
        $filePath = $runDir . DIRECTORY_SEPARATOR . $fileName;

        $this->writeCsv($filePath, $dataset['headers'], $dataset['rows']);
        $this->sessionArtifacts[] = $this->toRelativePath($filePath);

        $manifest = [
            'mode' => 'single',
            'generated_at' => date('c'),
            'course_source' => $this->courseSource,
            'options' => [
                'scope' => $scope,
                'scenario' => $scenario,
                'requested_count' => $count,
                'mixed_invalid_percent' => $mixedInvalidPercent,
            ],
            'files' => [
                [
                    'file' => $fileName,
                    'rows' => count($dataset['rows']),
                    'headers' => $dataset['headers'],
                    'notes' => $dataset['notes'],
                ],
            ],
        ];
        $manifestPath = $runDir . DIRECTORY_SEPARATOR . 'manifest.json';
        $this->writeManifest($manifestPath, $manifest);
        $this->sessionArtifacts[] = $this->toRelativePath($manifestPath);

        echo PHP_EOL;
        echo "Generated: " . $this->toRelativePath($filePath) . PHP_EOL;
        if ($dataset['notes'] !== []) {
            echo "Notes: " . implode(' | ', $dataset['notes']) . PHP_EOL;
        }
    }

    private function generateScenarioPack(): void
    {
        $count = $this->promptInt('Rows per file? [default 40]: ', 40, 1, 100000);
        $mixedInvalidPercent = $this->promptInt('Invalid row percentage for mixed files? [default 30]: ', 30, 1, 99);
        $includeHeaderIssues = $this->promptYesNo('Include header-invalid files? [Y/n]: ', true);

        $runDir = $this->makeRunDirectory('pack');
        $manifestFiles = [];

        foreach (YEAR_SCOPES as $scope => $scopeLabel) {
            foreach (SCENARIOS as $scenario => $scenarioLabel) {
                $dataset = $this->buildDataset($scope, $scenario, $count, $mixedInvalidPercent);
                $scopeSlug = $this->scopeSlug($scope);
                $scenarioSlug = str_replace('_', '-', $scenario);
                $fileName = $scopeSlug . '__' . $scenarioSlug . '__' . count($dataset['rows']) . '.csv';
                $filePath = $runDir . DIRECTORY_SEPARATOR . $fileName;

                $this->writeCsv($filePath, $dataset['headers'], $dataset['rows']);
                $relativePath = $this->toRelativePath($filePath);
                $this->sessionArtifacts[] = $relativePath;
                $manifestFiles[] = [
                    'file' => $fileName,
                    'scope' => $scopeLabel,
                    'scenario' => $scenarioLabel,
                    'rows' => count($dataset['rows']),
                    'notes' => $dataset['notes'],
                ];
            }
        }

        if ($includeHeaderIssues) {
            $headerDatasets = $this->buildHeaderIssueDatasets(max(5, min($count, 50)));
            foreach ($headerDatasets as $item) {
                $filePath = $runDir . DIRECTORY_SEPARATOR . $item['file_name'];
                $this->writeCsv($filePath, $item['headers'], $item['rows']);
                $relativePath = $this->toRelativePath($filePath);
                $this->sessionArtifacts[] = $relativePath;
                $manifestFiles[] = [
                    'file' => $item['file_name'],
                    'scope' => 'All years',
                    'scenario' => 'Header invalid',
                    'rows' => count($item['rows']),
                    'notes' => [$item['description']],
                ];
            }
        }

        $manifest = [
            'mode' => 'scenario_pack',
            'generated_at' => date('c'),
            'course_source' => $this->courseSource,
            'options' => [
                'count_per_file' => $count,
                'mixed_invalid_percent' => $mixedInvalidPercent,
                'include_header_issues' => $includeHeaderIssues,
            ],
            'files' => $manifestFiles,
        ];
        $manifestPath = $runDir . DIRECTORY_SEPARATOR . 'manifest.json';
        $this->writeManifest($manifestPath, $manifest);
        $this->sessionArtifacts[] = $this->toRelativePath($manifestPath);

        echo PHP_EOL;
        echo "Scenario pack generated in: " . $this->toRelativePath($runDir) . PHP_EOL;
        echo "Total files: " . count($manifestFiles) . " (+ manifest.json)" . PHP_EOL;
    }

    /**
     * @return array{headers: string[], rows: array<int, array<int, string>>, notes: string[]}
     */
    private function buildDataset(string $scope, string $scenario, int $count, int $mixedInvalidPercent): array
    {
        $notes = [];

        if ($scenario === 'valid_only') {
            $rows = $this->buildValidRows($scope, $count);
        } elseif ($scenario === 'invalid_only') {
            $rows = $this->buildInvalidRows($scope, $count);
        } elseif ($scenario === 'mixed') {
            $rows = $this->buildMixedRows($scope, $count, $mixedInvalidPercent);
            $notes[] = "Mixed mode: {$mixedInvalidPercent}% invalid target.";
        } elseif ($scenario === 'full_matrix') {
            $minimum = count(INVALID_CASES) + 1;
            if ($count < $minimum) {
                $notes[] = "Count increased from {$count} to {$minimum} to fit full invalid matrix.";
                $count = $minimum;
            }
            $rows = $this->buildFullMatrixRows($scope, $count);
        } else {
            throw new RuntimeException("Unknown scenario: {$scenario}");
        }

        return [
            'headers' => REQUIRED_HEADERS,
            'rows' => array_map(fn(array $row): array => $this->orderedRow($row), $rows),
            'notes' => $notes,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildValidRows(string $scope, int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = $this->buildValidRow($scope);
        }
        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildInvalidRows(string $scope, int $count): array
    {
        $rows = [];
        $caseCount = count(INVALID_CASES);
        for ($i = 0; $i < $count; $i++) {
            $case = INVALID_CASES[$i % $caseCount];
            $rows[] = $this->buildInvalidRow($scope, $case);
        }
        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildMixedRows(string $scope, int $count, int $invalidPercent): array
    {
        if ($count === 1) {
            return [$this->buildValidRow($scope)];
        }

        $invalidCount = (int) round($count * ($invalidPercent / 100));
        $invalidCount = max(1, min($count - 1, $invalidCount));
        $validCount = $count - $invalidCount;

        $rows = [];
        for ($i = 0; $i < $validCount; $i++) {
            $rows[] = $this->buildValidRow($scope);
        }
        for ($i = 0; $i < $invalidCount; $i++) {
            $case = INVALID_CASES[$i % count(INVALID_CASES)];
            $rows[] = $this->buildInvalidRow($scope, $case);
        }

        shuffle($rows);
        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildFullMatrixRows(string $scope, int $count): array
    {
        $rows = [];
        $rows[] = $this->buildValidRow($scope);

        foreach (INVALID_CASES as $case) {
            $rows[] = $this->buildInvalidRow($scope, $case);
        }

        $remaining = $count - count($rows);
        for ($i = 0; $i < $remaining; $i++) {
            if ($i % 2 === 0) {
                $rows[] = $this->buildValidRow($scope);
            } else {
                $case = INVALID_CASES[$i % count(INVALID_CASES)];
                $rows[] = $this->buildInvalidRow($scope, $case);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function buildValidRow(string $scope): array
    {
        $this->rowSeed++;
        $first = $this->firstNames[$this->rowSeed % count($this->firstNames)];
        $last = $this->lastNames[($this->rowSeed * 3) % count($this->lastNames)];
        $idNum = str_pad((string) (100000 + $this->rowSeed), 6, '0', STR_PAD_LEFT);
        $course = $this->courses[$this->rowSeed % count($this->courses)];
        $yearLevel = $scope === 'all'
            ? (string) (($this->rowSeed % 4) + 1)
            : $scope;
        $gender = ($this->rowSeed % 2 === 0) ? 'Male' : 'Female';

        return [
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first . '.' . $last . '.' . $idNum . '@dryrun.local'),
            'course' => $course,
            'year_level' => $yearLevel,
            'gender' => $gender,
            'student_id' => 'DRY' . $idNum,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildInvalidRow(string $scope, string $case): array
    {
        $row = $this->buildValidRow($scope);

        if ($case === 'missing_first_name') {
            $row['first_name'] = '';
        } elseif ($case === 'missing_last_name') {
            $row['last_name'] = '';
        } elseif ($case === 'missing_email') {
            $row['email'] = '';
        } elseif ($case === 'missing_course') {
            $row['course'] = '';
        } elseif ($case === 'missing_year_level') {
            $row['year_level'] = '';
        } elseif ($case === 'missing_gender') {
            $row['gender'] = '';
        } elseif ($case === 'missing_student_id') {
            $row['student_id'] = '';
        } elseif ($case === 'invalid_year_level') {
            $row['year_level'] = '7th';
        } elseif ($case === 'invalid_gender') {
            $row['gender'] = 'Unknown';
        } elseif ($case === 'unknown_course') {
            $row['course'] = 'Program Not In Catalog';
        } elseif ($case === 'multi_error') {
            $row['first_name'] = '';
            $row['course'] = 'Program Not In Catalog';
            $row['year_level'] = '0';
            $row['gender'] = 'X';
            $row['student_id'] = '';
        }

        return $row;
    }

    /**
     * @return array<int, array{file_name: string, headers: string[], rows: array<int, array<int, string>>, description: string}>
     */
    private function buildHeaderIssueDatasets(int $count): array
    {
        $validRows = $this->buildValidRows('all', $count);
        $datasets = [];

        foreach (REQUIRED_HEADERS as $missingHeader) {
            $headers = array_values(array_filter(REQUIRED_HEADERS, fn(string $header): bool => $header !== $missingHeader));
            $rows = [];
            foreach ($validRows as $row) {
                $cells = [];
                foreach ($headers as $header) {
                    $cells[] = $row[$header] ?? '';
                }
                $rows[] = $cells;
            }
            $datasets[] = [
                'file_name' => 'header-missing-' . $missingHeader . '.csv',
                'headers' => $headers,
                'rows' => $rows,
                'description' => "Missing required header: {$missingHeader}",
            ];
        }

        $typoHeaders = REQUIRED_HEADERS;
        $yearIndex = array_search('year_level', $typoHeaders, true);
        if ($yearIndex !== false) {
            $typoHeaders[$yearIndex] = 'yr_level';
        }

        $rows = array_map(fn(array $row): array => $this->orderedRow($row), $validRows);
        $datasets[] = [
            'file_name' => 'header-typo-year-level.csv',
            'headers' => $typoHeaders,
            'rows' => $rows,
            'description' => 'Invalid header alias: yr_level (should fail required header check).',
        ];

        return $datasets;
    }

    /**
     * @return array<int, string>
     */
    private function loadCoursesDbFirst(): array
    {
        $fallback = FALLBACK_COURSES;
        $envFile = $this->basePath . DIRECTORY_SEPARATOR . '.env';
        $env = $this->parseEnvFile($envFile);

        if (!extension_loaded('pdo_mysql')) {
            $this->courseSource = 'fallback (pdo_mysql extension missing)';
            echo "Course source: fallback list (pdo_mysql extension missing)." . PHP_EOL;
            return $fallback;
        }

        $dbName = trim((string) ($env['DB_DATABASE'] ?? ''));
        if ($dbName === '') {
            $this->courseSource = 'fallback (.env DB_DATABASE missing)';
            echo "Course source: fallback list (.env DB_DATABASE missing)." . PHP_EOL;
            return $fallback;
        }

        $host = trim((string) ($env['DB_HOST'] ?? '127.0.0.1'));
        $port = (int) ($env['DB_PORT'] ?? 3306);
        $user = (string) ($env['DB_USERNAME'] ?? 'root');
        $pass = (string) ($env['DB_PASSWORD'] ?? '');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 3,
            ]);

            $sql = "
                SELECT title AS course_name
                FROM program_catalogs
                WHERE title IS NOT NULL AND title <> ''
                UNION
                SELECT category AS course_name
                FROM program_catalogs
                WHERE category IS NOT NULL AND category <> ''
                ORDER BY course_name
            ";

            $stmt = $pdo->query($sql);
            $courses = [];
            foreach ($stmt as $row) {
                $value = trim((string) ($row['course_name'] ?? ''));
                if ($value !== '') {
                    $courses[] = $value;
                }
            }

            $courses = $this->uniqueStrings($courses);
            if ($courses !== []) {
                $this->courseSource = "db (program_catalogs, " . count($courses) . " values)";
                echo "Course source: {$this->courseSource}" . PHP_EOL;
                return $courses;
            }

            $this->courseSource = 'fallback (program_catalogs empty)';
            echo "Course source: fallback list (program_catalogs empty)." . PHP_EOL;
            return $fallback;
        } catch (Throwable $e) {
            $this->courseSource = 'fallback (DB query failed)';
            $short = trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '');
            echo "Course source: fallback list (DB query failed: {$short})." . PHP_EOL;
            return $fallback;
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseEnvFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $env = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if ($key === '') {
                continue;
            }

            if ($value !== '') {
                $starts = $value[0];
                $ends = $value[strlen($value) - 1];
                if (($starts === '"' && $ends === '"') || ($starts === "'" && $ends === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $env[$key] = $value;
        }

        return $env;
    }

    private function listGeneratedFiles(): void
    {
        if (!is_dir($this->outputRoot)) {
            echo "No output folder found yet." . PHP_EOL;
            return;
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->outputRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $ext = strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION));
            if ($ext !== 'csv' && $ext !== 'json') {
                continue;
            }
            $files[] = [
                'path' => $item->getPathname(),
                'mtime' => $item->getMTime(),
                'size' => $item->getSize(),
            ];
        }

        if ($files === []) {
            echo "No generated files yet under tests/dry-run-csv." . PHP_EOL;
            return;
        }

        usort($files, fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        echo PHP_EOL;
        echo "Generated files (newest first):" . PHP_EOL;
        $limit = min(30, count($files));
        for ($i = 0; $i < $limit; $i++) {
            $file = $files[$i];
            $relative = $this->toRelativePath($file['path']);
            $time = date('Y-m-d H:i:s', (int) $file['mtime']);
            echo sprintf("%2d. %s | %s | %d bytes", $i + 1, $time, $relative, $file['size']) . PHP_EOL;
        }

        if (count($files) > $limit) {
            echo "... and " . (count($files) - $limit) . " more file(s)." . PHP_EOL;
        }

        if ($this->sessionArtifacts !== []) {
            echo PHP_EOL . "Files generated in this session:" . PHP_EOL;
            foreach ($this->sessionArtifacts as $artifact) {
                echo "- " . $artifact . PHP_EOL;
            }
        }
    }

    private function makeRunDirectory(string $label): string
    {
        $stamp = date('Ymd-His');
        try {
            $rand = strtolower(bin2hex(random_bytes(2)));
        } catch (Throwable $e) {
            $rand = substr(md5((string) microtime(true)), 0, 4);
        }

        $safeLabel = preg_replace('/[^a-z0-9_-]+/i', '-', $label) ?? 'run';
        $safeLabel = trim($safeLabel, '-');
        if ($safeLabel === '') {
            $safeLabel = 'run';
        }

        $runDir = $this->outputRoot . DIRECTORY_SEPARATOR . $stamp . '-' . $safeLabel . '-' . $rand;
        $this->ensureDirectory($runDir);
        return $runDir;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     */
    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Failed to create file: {$path}");
        }

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeManifest(string $path, array $manifest): void
    {
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode manifest JSON.');
        }
        file_put_contents($path, $json . PHP_EOL);
    }

    /**
     * @param array<string, string> $row
     * @return array<int, string>
     */
    private function orderedRow(array $row): array
    {
        $ordered = [];
        foreach (REQUIRED_HEADERS as $header) {
            $ordered[] = (string) ($row[$header] ?? '');
        }
        return $ordered;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException("Unable to create directory: {$path}");
        }
    }

    private function scopeSlug(string $scope): string
    {
        if ($scope === 'all') {
            return 'all-years';
        }
        return 'year-' . $scope;
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function uniqueStrings(array $values): array
    {
        $seen = [];
        $result = [];
        foreach ($values as $value) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }
            $key = strtolower($trimmed);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $trimmed;
        }
        return $result;
    }

    /**
     * @param array<string, string> $options
     */
    private function promptChoice(string $title, array $options, string $defaultKey): string
    {
        $keys = array_keys($options);
        $defaultIndex = array_search($defaultKey, $keys, true);
        if ($defaultIndex === false) {
            $defaultIndex = 0;
            $defaultKey = $keys[0];
        }

        while (true) {
            echo PHP_EOL . $title . PHP_EOL;
            foreach ($keys as $idx => $key) {
                $label = $options[$key];
                echo ($idx + 1) . ". {$label} [{$key}]" . PHP_EOL;
            }

            $input = $this->readLine("Enter choice [default " . ($defaultIndex + 1) . "]: ");
            if ($input === '') {
                return $defaultKey;
            }

            if (ctype_digit($input)) {
                $num = (int) $input;
                if ($num >= 1 && $num <= count($keys)) {
                    return $keys[$num - 1];
                }
            }

            if (isset($options[$input])) {
                return $input;
            }

            echo "Invalid selection. Try again." . PHP_EOL;
        }
    }

    private function promptInt(string $prompt, int $default, int $min, int $max): int
    {
        while (true) {
            $input = $this->readLine($prompt);
            if ($input === '') {
                return $default;
            }
            if (ctype_digit($input)) {
                $value = (int) $input;
                if ($value >= $min && $value <= $max) {
                    return $value;
                }
            }
            echo "Enter a whole number between {$min} and {$max}." . PHP_EOL;
        }
    }

    private function promptYesNo(string $prompt, bool $defaultYes): bool
    {
        while (true) {
            $input = strtolower($this->readLine($prompt));
            if ($input === '') {
                return $defaultYes;
            }
            if ($input === 'y' || $input === 'yes') {
                return true;
            }
            if ($input === 'n' || $input === 'no') {
                return false;
            }
            echo "Type y or n." . PHP_EOL;
        }
    }

    private function readLine(string $prompt): string
    {
        echo $prompt;
        $line = fgets(STDIN);
        if ($line === false) {
            return '';
        }
        return trim($line);
    }

    private function toRelativePath(string $path): string
    {
        $normalizedBase = str_replace('\\', '/', $this->basePath);
        $normalizedPath = str_replace('\\', '/', $path);
        if (str_starts_with($normalizedPath, $normalizedBase . '/')) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }
        return $normalizedPath;
    }
}

(new CsvDryRunCreator(dirname(__DIR__)))->run();
