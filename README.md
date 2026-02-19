<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Project Bootstrap (Fresh PC)

For a new teammate machine, use the backend bootstrap script:

- Script: `scripts/laravel-fresh-start.ps1`
- Guide: `docs/BACKEND_FRESH_START.md`

Quick run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -Seed -Serve
```

XAMPP default quick run (root/no password + auto DB create):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -DatabaseName tclass_db -DbUser root -DbPassword "" -RootDbUser root -RootDbPassword "" -Seed -Serve
```

One-time fresh setup + admin creation (interactive):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -DatabaseName tclass_db -DbUser root -DbPassword "" -RootDbUser root -RootDbPassword "" -Seed -CreateAdmin -Serve
```

One-time fresh setup + admin creation (non-interactive):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -DatabaseName tclass_db -DbUser root -DbPassword "" -RootDbUser root -RootDbPassword "" -Seed -CreateAdmin -AdminName "Admin User" -AdminEmail "admin@tclass.local" -AdminPassword "Admin@12345" -Serve
```

## Create Admin User (Interactive Script)

For testing or teammate onboarding, use:

- Script: `scripts/create-admin-user.ps1`

### Interactive mode

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\create-admin-user.ps1
```

This will prompt for:

- Admin full name
- Admin email
- Admin password

### Non-interactive mode

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\create-admin-user.ps1 -Name "Admin User" -Email "admin@tclass.local" -Password "Admin@12345"
```

### Update existing admin password

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\create-admin-user.ps1 -Name \"Admin User\" -Email \"admin@tclass.local\" -Password \"NewStrongPass@123\" -ForceUpdatePassword
```

### Notes

- Script creates user if missing; updates name if existing.
- If `portal_user_roles` exists, it sets role `admin` with `is_active=1`.
- If Spatie roles are enabled, it also assigns `admin` role on `web` guard.

## PHP Extensions (Required)

For this backend (`php ^8.2`, Laravel 12), make sure these PHP extensions are enabled:

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

Recommended in dev:

- `zip`
- `intl`
- `gd`

### How to Enable Extensions (XAMPP / Windows)

1. Open your active `php.ini` (usually `C:\xampp\php\php.ini`).
2. Find each extension line and remove leading `;`.
3. Ensure lines like these are enabled:

```ini
extension=bcmath
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=xml
extension=zip
```

4. Save `php.ini`.
5. Restart Apache (and PHP service if applicable).
6. Verify loaded extensions:

```powershell
php --ini
php -m
```

### Quick Check for Missing Extensions

Run this in backend root:

```powershell
composer check-platform-reqs
```

If anything is missing, enable it in `php.ini`, restart Apache/PHP, then rerun the command.
