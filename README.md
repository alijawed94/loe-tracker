# LOE Tracker

Laravel 12 + React + Tailwind CSS application for tracking monthly level-of-effort submissions, employee allocations, and admin reporting.

## Stack

- Laravel 12
- React 19
- Tailwind CSS 4
- Sanctum session authentication
- MySQL (`loe_tracker`)
- Excel exports via `maatwebsite/excel`
- PDF exports via `barryvdh/laravel-dompdf`

## Core Features

- Separate login areas for employees and admins
- Role-based access using `admin` and `employee`
- ULID primary keys across domain models
- Employee monthly LOE submission with multiple projects and 100% cap
- Open-ended employee allocations managed by admins
- Admin CRUD for users, projects, and allocations
- Dashboard metrics and charts
- PDF and Excel exports
- Database + mail notifications
- Daily scheduled LOE reminder command

## Local Setup

1. Create the MySQL database:

```sql
CREATE DATABASE loe_tracker;
```

2. Install PHP dependencies:

```powershell
composer install
```

3. Install frontend dependencies:

```powershell
corepack pnpm install
```

4. Build assets:

```powershell
corepack pnpm build
```

5. Run migrations and seeders:

```powershell
php artisan migrate --seed
```

6. Start Laravel:

```powershell
php artisan serve
```

7. For frontend development:

```powershell
corepack pnpm dev
```

## Default Seeded Credentials

- Super admin email: `ali.jawed@pixeledge.io`
- Seed password: `Password@123`

Sample employee accounts are seeded with the same password.

## Scheduled Reminders

The reminder command is:

```powershell
php artisan loe:send-reminders
```

It is registered on the Laravel scheduler and should be triggered by cron / Windows Task Scheduler through:

```powershell
php artisan schedule:run
```
