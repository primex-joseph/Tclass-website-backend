# TClass Backend (Laravel API)

Backend API for admissions, enrollment, curriculum, scheduling, and portal auth.

## Stack
- Laravel 12
- PHP 8.2+
- MySQL/MariaDB
- Sanctum auth

## Quick Start
```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --force
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Backend URL: `http://127.0.0.1:8000`
API Base: `http://127.0.0.1:8000/api`

## Core Modules
- Auth (`/api/auth/*`)
- Student Enrollment flow (`/api/student/*`)
- Admin Enrollment approvals (`/api/admin/enrollments`)
- Curriculum management (`/api/admin/curricula`)
- Class Scheduling (`/api/admin/scheduling/*`)
- Contact + admissions

## Route Organization (Current)
- API routes are split by domain and loaded from `routes/api.php`:
  - `routes/api/auth.php`
  - `routes/api/student.php`
  - `routes/api/admin.php`
  - `routes/api/admission.php`
  - `routes/api/contact.php`
  - `routes/api/scheduling.php`
- Middleware and endpoints are preserved from the previous monolithic route file.

## CORS (Local Development)
- CORS config: `config/cors.php`
- Configure allowed SPA origins in `.env`:
  - `CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000`
- `FRONTEND_URL` remains the primary frontend URL.
- `SANCTUM_STATEFUL_DOMAINS` should include local SPA hosts for cookie auth.
- When changing frontend origin(s), run:
  - `php artisan optimize:clear`
- If MySQL is not running yet, use:
  - `php artisan config:clear`

## Scheduling + Curriculum Notes
- Curriculum is stored in:
  - `curriculum_versions`
  - `curriculum_subjects`
- Active curriculum syncs to `courses`.
- Scheduling creates/updates `class_offerings`.
- Student schedule/enrollment consume offerings.

## Enrollment Period Rollover
- API: `POST /api/admin/enrollment-periods/rollover`
- CLI: `php artisan enrollment:rollover`
- Transition order:
  - 1st Semester -> 2nd Semester -> Summer -> next AY 1st Semester

## Docs
- `docs/backend-setup.md`
- `docs/BACKEND_FRESH_START.md`

## Run the project
- php artisan serve --host=0.0.0.0 --port=8000
- npm run dev -- --webpack
