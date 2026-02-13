# Backend Setup (Laravel) - Step by Step

Project path used in this guide:

`C:\xampp\htdocs\tclass-v1-backend`

## 1. Prerequisites

Install/prepare:

- PHP 8.2+ (XAMPP is fine)
- Composer
- MySQL/MariaDB (XAMPP MySQL is fine)
- A database named `tclass`

## 2. Open backend folder

```powershell
cd C:\xampp\htdocs\tclass-v1-backend
```

## 3. Install PHP dependencies

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

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tclass
DB_USERNAME=root
DB_PASSWORD=
```

## 5. Configure mail (Gmail SMTP for real email sending)

Use app password (not your normal Gmail password):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=tclasstarlac26@gmail.com
MAIL_PASSWORD=your_16_char_gmail_app_password
MAIL_FROM_ADDRESS=tclasstarlac26@gmail.com
MAIL_FROM_NAME="TClass"
```

## 6. Generate app key

```powershell
php artisan key:generate
```

## 7. Run migrations

```powershell
php artisan migrate
```

## 8. Create storage symlink (for uploaded images)

```powershell
php artisan storage:link
```

## 9. Start backend server

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

API base URL should now be:

`http://127.0.0.1:8000/api`

## 10. Quick verification

Check API route quickly in browser/Postman:

- `POST /api/admission/submit`
- `POST /api/auth/login`

## 11. Notes for current admission feature

Current backend already supports:

- Full admission form submit
- File uploads (`id_picture`, `one_by_one_picture`, `right_thumbmark`)
- Admin approve (creates student account + sends credentials email)
- Admin reject (requires reason + sends rejection email)
