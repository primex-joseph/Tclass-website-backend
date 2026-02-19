param(
  [switch]$Seed,
  [switch]$Serve,
  [switch]$ForceEnvCopy,
  [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

function Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }
function Warn($msg) { Write-Host "[WARN] $msg" -ForegroundColor Yellow }

Step "Laravel fresh-start bootstrap"

if (-not (Test-Path "artisan")) {
  throw "Run this script from the Laravel backend root (folder containing artisan)."
}

Step "Checking required tools"
php -v | Out-Null
composer --version | Out-Null

Step "Installing PHP dependencies"
composer install --no-interaction --prefer-dist

if ($ForceEnvCopy -or -not (Test-Path ".env")) {
  Step "Preparing .env file"
  if (-not (Test-Path ".env.example")) {
    throw ".env.example not found."
  }
  Copy-Item ".env.example" ".env" -Force
  Write-Host "Created .env from .env.example"
} else {
  Warn ".env exists; keeping current file (use -ForceEnvCopy to overwrite)."
}

Step "Generating app key"
php artisan key:generate --force

Step "Creating storage symlink (if needed)"
try {
  php artisan storage:link
} catch {
  Warn "storage:link skipped/failed (may already exist)."
}

Step "Clearing caches"
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear

Step "Running fresh migrations"
if ($Seed) {
  php artisan migrate:fresh --seed --force
} else {
  php artisan migrate:fresh --force
}

Step "Done"
Write-Host "Backend is reset and ready." -ForegroundColor Green
Write-Host "Next: configure DB/MAIL values in .env if needed." -ForegroundColor Green

if ($Serve) {
  Step "Starting Laravel server on port $Port"
  php artisan serve --host=127.0.0.1 --port=$Port
}
