# Backend Fresh Start Script

This backend repo includes a one-command bootstrap script for a new teammate PC.

## File

- `scripts/laravel-fresh-start.ps1`

## What it does

1. Checks `php` and `composer`
2. Runs `composer install`
3. Creates `.env` from `.env.example` (if missing)
4. Runs `php artisan key:generate`
5. Runs `php artisan storage:link`
6. Clears caches (`optimize:clear`, `config:clear`, `cache:clear`)
7. Runs `php artisan migrate:fresh` (optional seed)
8. Optionally starts `php artisan serve`

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

## Examples

Fresh setup with seeding and local server:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -Seed -Serve
```

Fresh migration only (keep current `.env`):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1
```

Force reset `.env` then fresh migrate:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -ForceEnvCopy
```
