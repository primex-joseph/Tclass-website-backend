# Backend Setup (Laravel)

Path used:
`C:\xampp\htdocs\tclass-v1-backend`

## 1) Prerequisites
- PHP 8.2+
- Composer
- MySQL/MariaDB

## 2) Install
```powershell
cd C:\xampp\htdocs\tclass-v1-backend
composer install
copy .env.example .env
php artisan key:generate
```

## 3) Configure `.env`
```env
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tclass_db
DB_USERNAME=root
DB_PASSWORD=
```

## 4) DB + Storage
```powershell
php artisan migrate
php artisan db:seed --force
php artisan storage:link
```

## 5) Run
```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

## 6) Key API Checks
- `GET /api/admin/curricula`
- `GET /api/admin/curricula/{id}/subjects`
- `GET /api/admin/scheduling/masters`
- `GET /api/admin/scheduling/offerings`
- `POST /api/admin/scheduling/offerings/upsert`
- `POST /api/admin/enrollment-periods/rollover`

## 7) Period Control
- UI button in class scheduling: `Advance Period`
- CLI fallback:
```powershell
php artisan enrollment:rollover
```
