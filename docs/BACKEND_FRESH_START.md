# Backend Fresh Start Script

Script: `scripts/setup/laravel-fresh-start.ps1`

## One-command setup
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup\laravel-fresh-start.ps1 -DatabaseName tclass_db -DbUser root -DbPassword "" -RootDbUser root -RootDbPassword "" -Seed -CreateAdmin -Serve
```

## What it does
1. Installs dependencies
2. Creates/updates `.env`
3. Creates database
4. Runs key generate + storage link
5. Runs `migrate:fresh` (+ seed when requested)
6. Optionally creates admin user
7. Starts local server

## Important after fresh start
- Verify curriculum and scheduling routes are available.
- Verify seeded sections include BSIT 1A/1B/1C ... 4A/4B/4C.
- Verify rollover command:
```powershell
php artisan enrollment:rollover
```

## Useful flags
- `-Seed`
- `-CreateAdmin`
- `-Serve`
- `-ForceEnvCopy`
- `-Port 8000`
