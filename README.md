# TClass Backend (Laravel API)

Backend API for the TClass system, supporting admissions, vocational enrollment, portal authentication, admin operations, and messaging.

## Core Modules

- Authentication (`/api/auth/*`) with portal role checks (`student`, `faculty`, `admin`)
- Admission and vocational submission workflows
- Enrollment lifecycle endpoints for student/admin flows
- Admin dashboard APIs (stats, users, trends data)
- Contact form processing + admin inbox message persistence
- Mail notifications (contact, admission approved/rejected)

## Tech Stack

- Laravel 12
- PHP 8.2+
- MySQL/MariaDB
- Laravel Sanctum
- Spatie Permission (role support where enabled)

## Quick Start (Fresh PC)

Use the one-shot bootstrap script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -DatabaseName tclass_db -DbUser root -DbPassword "" -RootDbUser root -RootDbPassword "" -Seed -CreateAdmin -Serve
```

This will:

1. Install dependencies
2. Create/configure `.env`
3. Create database
4. Run `migrate:fresh --seed`
5. Optionally create admin account
6. Start local server

Full guide: `docs/BACKEND_FRESH_START.md`

## Manual Setup

If you prefer manual setup, follow:

- `docs/backend-setup.md`

## Required Environment Keys

Minimum required in `.env`:

```env
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tclass_db
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your_account@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_FROM_ADDRESS=your_account@gmail.com
MAIL_FROM_NAME="Tarlac Center for Learning and Skills Success"

CONTACT_RECEIVER_EMAIL=your_inbox@gmail.com
```

After env changes:

```powershell
php artisan config:clear
php artisan cache:clear
```

## Required PHP Extensions

Enable these extensions in `php.ini`:

- `bcmath`
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `json`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_mysql`
- `tokenizer`
- `xml`

Recommended in dev: `zip`, `intl`, `gd`

Verify:

```powershell
php -m
composer check-platform-reqs
```

## Dev Test Accounts

Seeded accounts (from `DatabaseSeeder`):

- Faculty: `facultydev@tclass.local` / `Faculty123!`
- Student: `studentdev@tclass.local` / `Student123!`

Admin account can be created with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\create-admin-user.ps1
```

## Common Commands

```powershell
php artisan migrate
php artisan db:seed --force
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

## Docs Index

- `docs/backend-setup.md`
- `docs/BACKEND_FRESH_START.md`
