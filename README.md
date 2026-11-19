# ZPI

Zonal performance system for an Ethiopian zone administration. Staff plan and report against department KPI indicators, HR records Monday–Friday attendance around Ethiopian holidays and approved leave, and finance calculates performance-based payroll. The budget year runs from Hamle 1 to Sene 30. Reporting weeks run Monday to Sunday.

The application is Laravel 12 on PHP 8.2+ with MySQL (`utf8mb4` / `utf8mb4_unicode_ci`), Bootstrap 5, and Chart.js. Amharic and English are both included.

## Requirements

- PHP 8.2 or newer with `mbstring`, `xml`, `curl`, `zip`, `gd`, `intl`, `bcmath`, and `pdo_mysql`
- Composer
- MySQL 8 (or MariaDB) using `utf8mb4_unicode_ci`
- Node.js 20+ for the front-end build

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set the database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zpi
DB_USERNAME=zpi
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Create the database, then:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`. The scheduler command `reporting:lock-expired` is registered in `routes/console.php` and should run daily:

```bash
php artisan schedule:work
```

## Seeded accounts

Every seeded account uses the password `Password@123`. Change these before any shared deployment.

| Role | Email |
| --- | --- |
| Super admin | admin@zpi.gov.et |
| Zone chief | chief@zpi.gov.et |
| Zone deputy | deputy@zpi.gov.et |
| Finance | finance@zpi.gov.et |
| Planning | plan@zpi.gov.et |
| Health department head | head.health@zpi.gov.et |
| Health HR specialist | hr.health@zpi.gov.et |
| Health development-plan specialist | planning.health@zpi.gov.et |
| Health employee | employee.health@zpi.gov.et |
| Education department head | head.education@zpi.gov.et |
| Education HR specialist | hr.education@zpi.gov.et |
| Education development-plan specialist | planning.education@zpi.gov.et |
| Education employee | employee.education@zpi.gov.et |
| Agriculture department head | head.agriculture@zpi.gov.et |
| Agriculture HR specialist | hr.agriculture@zpi.gov.et |
| Agriculture development-plan specialist | planning.agriculture@zpi.gov.et |
| Agriculture employee | employee.agriculture@zpi.gov.et |

## Roles

Department roles (employee, HR specialist, development-plan specialist, department head) only see their own active department. An acting head can approve plans, leave, and weekly summaries until `acting_until`. Historical plans, attendance, and payroll keep the department they were recorded under when someone is transferred.

Central roles are finance, planning, zone chief, zone deputy, and super admin. Zone chief and zone deputy can read cross-department KPIs, leaderboards, HR counts, and payroll totals, and they can send directives. They cannot change users or system settings. Only the super admin manages departments, accounts, the KPI catalog, holidays, settings, and the audit log.

Payroll uses base salary, the approved monthly performance score, and HR-verified attendance. Holidays and approved leave are not penalized. There is no flat cash deduction. Weights default to 70% attendance and 30% performance and must sum to 1.

Approved weekly reports roll up into monthly, quarterly, 6-month, and annual totals. Expired periods are locked by `php artisan reporting:lock-expired`. Only the department head or the super admin can unlock a period.

## Tests

The PHPUnit suite uses SQLite in memory, configured in `phpunit.xml`. MySQL is not required to run it.

```bash
php artisan test
```

## Language

Use the language switch in the header. Personal preferences are saved at `/settings`. Institution name, logo, budget year, lock grace days, and KPI weights are at `/admin/settings` for the super admin.

Official report PDFs and paid payroll receipts embed Noto Sans Ethiopic and a QR code that opens `/verify/{hash}`.
