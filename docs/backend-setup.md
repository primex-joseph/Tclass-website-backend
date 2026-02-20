# Backend Setup (Laravel) - Step by Step

Project path used in this guide:

`C:\xampp\htdocs\tclass-v1-backend`

## 1. Prerequisites

Install/prepare:

- PHP 8.2+ (XAMPP is fine)
- Composer
- MySQL/MariaDB (XAMPP MySQL is fine)

## 2. Open backend folder

```powershell
cd C:\xampp\htdocs\tclass-v1-backend
```

## 3. Install dependencies

```powershell
composer install
```

## 4. Configure environment

Copy `.env.example` to `.env` if needed:

```powershell
copy .env.example .env
```

Update these values in `.env`:

```env
APP_NAME=TClass
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tclass_db
DB_USERNAME=root
DB_PASSWORD=
```

## 5. Configure mail (for real email sending)

Use app password (not normal Gmail password):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=tclasstarlac26@gmail.com
MAIL_PASSWORD=your_16_char_gmail_app_password
MAIL_FROM_ADDRESS=tclasstarlac26@gmail.com
MAIL_FROM_NAME="Tarlac Center for Learning and Skills Success"

CONTACT_RECEIVER_EMAIL=your_inbox@gmail.com
```

## 6. Generate app key

```powershell
php artisan key:generate
```

## 7. Run migrations

```powershell
php artisan migrate
```

Current important migrations include:

- `2026_02_19_030000_create_portal_user_roles_table`
- `2026_02_19_050000_create_contact_messages_table`
- `2026_02_20_090000_add_enrollment_purposes_to_admission_applications`

## 8. (Optional) Seed dev data

```powershell
php artisan db:seed --force
```

Seeded test accounts:

- Faculty: `facultydev@tclass.local` / `Faculty123!`
- Student: `studentdev@tclass.local` / `Student123!`

## 9. Create admin account

Interactive:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\create-admin-user.ps1
```

Or use one-shot fresh script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\laravel-fresh-start.ps1 -DatabaseName tclass_db -DbUser root -DbPassword "" -RootDbUser root -RootDbPassword "" -Seed -CreateAdmin -Serve
```

## 10. Create storage symlink (for uploads)

```powershell
php artisan storage:link
```

## 11. Clear cache after env/config changes

```powershell
php artisan config:clear
php artisan cache:clear
```

## 12. Start backend server

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

API base URL:

`http://127.0.0.1:8000/api`

## 13. Quick verification endpoints

- `POST /api/auth/login`
- `POST /api/admission/submit`
- `POST /api/contact/submit`
- `GET /api/admin/users`
- `GET /api/admin/dashboard-stats`
- `GET /api/admin/contact-messages`

## 14. Troubleshooting

- `SQLSTATE ... table ... doesn't exist`
  - Run `php artisan migrate`

- Login fails for admin role checks:
  - Ensure `portal_user_roles` exists and admin row is active (`role=admin`, `is_active=1`)

- Emails not received:
  - Verify `MAIL_*` and `CONTACT_RECEIVER_EMAIL`
  - Check spam folder
  - Restart server after env updates

- CORS/auth issues with frontend:
  - Ensure frontend uses `NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api`
