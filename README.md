# LOE Tracker

LOE Tracker is a Laravel + React application for managing monthly level-of-effort submissions, employee allocations, admin reviews, feedback, reporting, and operational visibility.

It is designed to reduce the effort required from employees while giving admins and leadership a cleaner way to monitor submission compliance, utilization patterns, allocation coverage, and project health.

## Highlights

- Separate employee and admin login areas
- Role-based access with `admin` and `employee` roles
- ULID primary keys across domain models
- Monthly LOE entry with project-wise percentage breakdown
- Open-ended employee allocations managed by admins
- LOE workflow with draft, submitted, and approved states
- Admin review and feedback threads on LOEs
- In-app notifications
- Activity logs for operational changes
- Dashboard metrics, exceptions, and allocation coverage chart
- PDF and Excel exports for user-specific LOE history
- Search and filtering across admin modules

## Tech Stack

- Laravel 12
- React 19
- Tailwind CSS 4
- Sanctum for session authentication
- MySQL database: `loe_tracker`
- `maatwebsite/excel` for Excel export
- `barryvdh/laravel-dompdf` for PDF export
- `spatie/laravel-activitylog` for audit logging
- `react-select` for enhanced multi-select inputs
- `@heroicons/react` for UI icons

## Main Modules

### Employee Panel

- Login through `/login`
- Create LOE submissions by month and year
- Add multiple project entries inside one LOE
- Prevent duplicate LOEs for the same month and year
- Save LOEs as draft or submit them
- Edit LOEs before deadline rules apply
- Delete LOEs with extra confirmation for expired periods
- View previous submissions in grouped tabular format
- View and reply to admin feedback
- View in-app notifications
- See a live deadline countdown on the dashboard

### Admin Panel

- Login through `/admin/login`
- Dashboard with metrics, exceptions, and project allocation headcount graph
- CRUD for users, projects, and allocations
- Search and filtering across admin modules
- Review employee LOEs and approve them
- View per-user LOE history with grouped breakdowns
- Export each user's LOE history to PDF or Excel
- View feedback threads on LOEs
- View activity logs with readable details modal
- View notifications inside the panel

## Project Structure

- Backend: Laravel application logic, API routes, models, policies, notifications, exports, and tests
- Frontend: React app mounted inside Laravel and bundled with Vite
- Database: MySQL with Laravel migrations and seeders
- Authentication: Sanctum session-based auth for both panels

## Getting Started

### 1. Clone the Repository

```powershell
git clone <your-repository-url>
cd loe_tracker
```

### 2. Install PHP Dependencies

```powershell
composer install
```

### 3. Install Frontend Dependencies

```powershell
corepack pnpm install
```

### 4. Create the Environment File

```powershell
Copy-Item .env.example .env
```

Update the database values inside `.env` as needed. For the expected local setup:

```env
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=loe_tracker
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Create the Database

```sql
CREATE DATABASE loe_tracker;
```

### 6. Generate the Application Key

```powershell
php artisan key:generate
```

### 7. Run Fresh Migrations with Seed Data

```powershell
php artisan migrate:fresh --seed
```

### 8. Build Frontend Assets

```powershell
corepack pnpm build
```

For local frontend development, run:

```powershell
corepack pnpm dev
```

### 9. Start the Laravel Development Server

```powershell
php artisan serve
```

Open the app in your browser using `APP_URL` from your `.env` file:

- Employee login: `${APP_URL}/login`
- Admin login: `${APP_URL}/admin/login`

## Seeded Demo Credentials

### Admin

- Email: `admin@example.com`
- Password: `Password@1`

### Employee

- Email: `employee@example.com`
- Password: `Password@1`

## Notifications

The application currently keeps notifications at the database level so they appear in both the admin and employee dashboards.

Email notification code is still present in the codebase, but mail delivery is intentionally disabled for now until a working mail service is configured.

## Scheduled Tasks

The project includes reminder scheduling for LOE notifications.

Run the reminder command manually with:

```powershell
php artisan loe:send-reminders
```

To run all scheduled tasks locally:

```powershell
php artisan schedule:run
```

For production-like scheduling, configure cron or Windows Task Scheduler to trigger Laravel scheduler every minute.

## Testing

Run the full test suite with:

```powershell
php artisan test
```

You can also run targeted suites such as:

```powershell
php artisan test tests\Feature\AuthApiTest.php
php artisan test tests\Feature\EmployeeApiTest.php
php artisan test tests\Feature\AdminApiTest.php
```

## Notes

- Password reset UI is hidden because email delivery is currently disabled.
- In-app notifications, audit logs, and workflow tracking remain active.
- If `corepack pnpm build` hits a local permission issue, run it with the permissions needed by your machine's Node/Corepack setup.

## License

This project is intended for internal or custom application use unless you choose to apply a separate license.
