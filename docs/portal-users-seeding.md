# Portal Users Seeding Guide

This project now uses a dedicated seeder for role-based accounts:

- `Database\\Seeders\\PortalUsersSeeder`

It creates all base users, assigns `portal_user_roles`, and seeds related defaults such as student admission records and faculty profile metadata (when relevant tables exist).

## Seed Commands

Run a full fresh setup:

```bash
php artisan migrate:fresh --seed
```

Run only the users seeder:

```bash
php artisan db:seed --class=PortalUsersSeeder
```

Run users + faculty domain seeders:

```bash
php artisan db:seed --class=PortalUsersSeeder
php artisan db:seed --class=FacultyPortalSeeder
```

## Seeded Credentials

These are the default seeded accounts:

| Role | Email | Password |
| --- | --- | --- |
| admin | `admindev@tclass.local` | `Admin123!` |
| admin | `adminsupport@tclass.local` | `AdminSupport123!` |
| faculty | `facultydev@tclass.local` | `Faculty123!` |
| faculty | `registrar.faculty@tclass.local` | `Registrar123!` |
| student | `studentdev@tclass.local` | `Student123!` |
| student | `facultystudent1@tclass.local` | `Student123!` |
| student | `facultystudent2@tclass.local` | `Student123!` |

## Notes

- `PortalUsersSeeder` is called automatically by `DatabaseSeeder`.
- `FacultyPortalSeeder` expects these users to exist and then attaches faculty workflow/sample data.
- Seeder execution is idempotent by email and role, so re-running is safe for local development.
