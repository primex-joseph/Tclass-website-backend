# Backend Fresh Start Script

This backend repo includes a one-command bootstrap script for a new teammate PC.

## File

- `scripts/laravel-fresh-start.ps1`

## What it does

1. Checks `php` and `composer`
2. Runs `composer install`
3. Creates `.env` from `.env.example` (if missing)
4. Updates `.env` DB values (`DB_DATABASE`, `DB_USERNAME`, etc.)
5. Auto-creates MySQL database
6. Optionally creates/grants DB user (if `-DbUser` is not `root`)
7. Runs `php artisan key:generate`
8. Runs `php artisan storage:link`
9. Clears caches (`optimize:clear`, `config:clear`, `cache:clear`)
10. Runs `php artisan migrate:fresh` (optional seed)
11. Optionally creates admin user (`scripts/create-admin-user.ps1`)
12. Optionally starts `php artisan serve`

## Usage

Run this from backend root (where `artisan` exists):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -Seed -Serve
```

## Flags

- `-Seed` : run `migrate:fresh --seed`
- `-Serve` : run `php artisan serve` after setup
- `-ForceEnvCopy` : overwrite existing `.env` with `.env.example`
- `-Port 9000` : custom Laravel serve port
- `-DatabaseName tclass_db` : DB name to create/use (default: `tclass_db`)
- `-DbUser root` : app DB user (default: `root`)
- `-DbPassword ""` : app DB password (default: empty)
- `-DbHost 127.0.0.1` : DB host (default: `127.0.0.1`)
- `-DbPort 3306` : DB port (default: `3306`)
- `-RootDbUser root` : MySQL admin/root user used for CREATE DATABASE
- `-RootDbPassword ""` : MySQL admin/root password
- `-CreateAdmin` : run admin creation step after migrations
- `-AdminName "Admin User"` : admin full name (optional; prompts if missing)
- `-AdminEmail "admin@tclass.local"` : admin email (optional; prompts if missing)
- `-AdminPassword "Admin@12345"` : admin password (optional; prompts if missing)
- `-ForceAdminPasswordUpdate` : force-update password if admin already exists

## Examples

Fresh setup with seeding and local server:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -Seed -Serve
```

XAMPP default (root, no password), explicit DB name:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -DatabaseName tclass_db -DbUser root -DbPassword "" -RootDbUser root -RootDbPassword "" -Seed -Serve
```

One-time fresh setup + interactive admin creation:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -DatabaseName tclass_db -DbUser root -DbPassword "" -RootDbUser root -RootDbPassword "" -Seed -CreateAdmin -Serve
```

One-time fresh setup + non-interactive admin creation:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -DatabaseName tclass_db -DbUser root -DbPassword "" -RootDbUser root -RootDbPassword "" -Seed -CreateAdmin -AdminName "Admin User" -AdminEmail "admin@tclass.local" -AdminPassword "Admin@12345" -Serve
```

Fresh migration only (keep current `.env`):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1
```

Force reset `.env` then fresh migrate:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -ForceEnvCopy
```
