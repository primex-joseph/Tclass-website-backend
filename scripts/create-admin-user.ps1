param(
  [string]$Name,
  [string]$Email,
  [string]$Password,
  [switch]$ForceUpdatePassword
)

$ErrorActionPreference = "Stop"

function Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }
function Warn($msg) { Write-Host "[WARN] $msg" -ForegroundColor Yellow }

if (-not (Test-Path "artisan")) {
  throw "Run this script from Laravel backend root (where artisan exists)."
}

if (-not $Name) { $Name = Read-Host "Admin full name" }
if (-not $Email) { $Email = Read-Host "Admin email" }
if (-not $Password) {
  $secure = Read-Host "Admin password" -AsSecureString
  $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
  try { $Password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}

if (-not $Name -or -not $Email -or -not $Password) {
  throw "Name, Email, and Password are required."
}

if ($Password.Length -lt 8) {
  throw "Password must be at least 8 characters."
}

Step "Creating/updating admin user"

$phpScript = @"
<?php
require __DIR__ . '/vendor/autoload.php';

`$app = require __DIR__ . '/bootstrap/app.php';
`$kernel = `$app->make(Illuminate\Contracts\Console\Kernel::class);
`$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

`$name = getenv('ADMIN_NAME');
`$email = strtolower(trim(getenv('ADMIN_EMAIL')));
`$password = getenv('ADMIN_PASSWORD');
`$force = getenv('ADMIN_FORCE_PASSWORD') === '1';

if (!filter_var(`$email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email format.`n");
    exit(1);
}

`$existing = User::query()->where('email', `$email)->first();

if (`$existing) {
    `$existing->name = `$name;
    if (`$force) {
        `$existing->password = Hash::make(`$password);
    }
    if (property_exists(`$existing, 'must_change_password')) {
        `$existing->must_change_password = false;
    }
    `$existing->save();
    `$user = `$existing;
    `$action = 'updated';
} else {
    `$payload = [
        'name' => `$name,
        'email' => `$email,
        'password' => Hash::make(`$password),
    ];

    // Optional column in this project
    if (Schema::hasColumn('users', 'must_change_password')) {
        `$payload['must_change_password'] = false;
    }

    `$user = User::query()->create(`$payload);
    `$action = 'created';
}

// Project-specific auth table
if (Schema::hasTable('portal_user_roles')) {
    DB::table('portal_user_roles')->updateOrInsert(
        ['user_id' => `$user->id, 'role' => 'admin'],
        ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
    );
}

// Spatie roles (if used)
if (class_exists(Role::class)) {
    `$role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    if (method_exists(`$user, 'assignRole')) {
        `$user->syncRoles([`$role->name]);
    }
}

echo json_encode([
  'status' => 'ok',
  'action' => `$action,
  'user_id' => `$user->id,
  'name' => `$user->name,
  'email' => `$user->email,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
"@

$tmpPath = Join-Path $env:TEMP "tclass_create_admin.php"
Set-Content -Path $tmpPath -Value $phpScript -Encoding UTF8

$env:ADMIN_NAME = $Name
$env:ADMIN_EMAIL = $Email
$env:ADMIN_PASSWORD = $Password
$env:ADMIN_FORCE_PASSWORD = $(if ($ForceUpdatePassword) { "1" } else { "0" })

try {
  $result = php $tmpPath
  if ($LASTEXITCODE -ne 0) { throw "Admin creation script failed." }
  Write-Host $result -ForegroundColor Green
  Write-Host "Done. Admin is ready to login (role: admin)." -ForegroundColor Green
}
finally {
  Remove-Item $tmpPath -ErrorAction SilentlyContinue
  Remove-Item Env:ADMIN_NAME -ErrorAction SilentlyContinue
  Remove-Item Env:ADMIN_EMAIL -ErrorAction SilentlyContinue
  Remove-Item Env:ADMIN_PASSWORD -ErrorAction SilentlyContinue
  Remove-Item Env:ADMIN_FORCE_PASSWORD -ErrorAction SilentlyContinue
}
