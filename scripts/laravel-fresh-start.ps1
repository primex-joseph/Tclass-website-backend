param(
  [switch]$Seed,
  [switch]$Serve,
  [switch]$ForceEnvCopy,
  [string]$DatabaseName = "tclass_db",
  [string]$DbHost = "127.0.0.1",
  [int]$DbPort = 3306,
  [string]$DbUser = "root",
  [string]$DbPassword = "",
  [string]$RootDbUser = "root",
  [string]$RootDbPassword = "",
  [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

function Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }
function Warn($msg) { Write-Host "[WARN] $msg" -ForegroundColor Yellow }
function Set-EnvValue($Path, $Key, $Value) {
  $pattern = "^(?:\s*)$([regex]::Escape($Key))=.*$"
  $line = "$Key=$Value"
  if (Select-String -Path $Path -Pattern $pattern -Quiet) {
    (Get-Content $Path) -replace $pattern, $line | Set-Content $Path
  } else {
    Add-Content -Path $Path -Value $line
  }
}

function Escape-SqlLiteral($text) {
  return ($text -replace "'", "''")
}

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

Step "Configuring database and .env DB values"
$envPath = ".env"

Set-EnvValue $envPath "DB_CONNECTION" "mysql"
Set-EnvValue $envPath "DB_HOST" $DbHost
Set-EnvValue $envPath "DB_PORT" $DbPort
Set-EnvValue $envPath "DB_DATABASE" $DatabaseName
Set-EnvValue $envPath "DB_USERNAME" $DbUser
Set-EnvValue $envPath "DB_PASSWORD" $DbPassword

$mysqlCmd = Get-Command mysql -ErrorAction SilentlyContinue
$mysqlExe = $null
if ($mysqlCmd) {
  $mysqlExe = $mysqlCmd.Source
} elseif (Test-Path "C:\xampp\mysql\bin\mysql.exe") {
  $mysqlExe = "C:\xampp\mysql\bin\mysql.exe"
}

if (-not $mysqlExe) {
  Warn "mysql client not found. Skipping DB creation. Ensure DB '$DatabaseName' exists before migrate."
} else {
  $dbSafe = ($DatabaseName -replace "[^A-Za-z0-9_]", "")
  if (-not $dbSafe) {
    throw "Invalid database name: '$DatabaseName'"
  }
  $sql = "CREATE DATABASE IF NOT EXISTS $dbSafe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

  if ($DbUser -ne "root") {
    $userEsc = Escape-SqlLiteral $DbUser
    $passEsc = Escape-SqlLiteral $DbPassword
    $sql += " CREATE USER IF NOT EXISTS '$userEsc'@'localhost' IDENTIFIED BY '$passEsc';"
    $sql += " GRANT ALL PRIVILEGES ON $dbSafe.* TO '$userEsc'@'localhost'; FLUSH PRIVILEGES;"
  }

  $args = @("-u", $RootDbUser)
  if ($RootDbPassword) {
    $args += "-p$RootDbPassword"
  }
  $args += @("-e", $sql)

  & $mysqlExe @args
  Write-Host "Database '$DatabaseName' is ready."
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
