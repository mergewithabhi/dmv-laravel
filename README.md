# DMV Warriors CMS

Production Laravel CMS for the DMV Warriors website. The application preserves the six approved page designs while making all public copy, media, icons, links, labels, people, games, sponsors, forms, and global chrome editable.

## Stack

- Laravel 13, PHP 8.3+, Livewire 4, Blade, Vite
- MySQL for local, testing, staging, and production
- Laravel Fortify with verified accounts, throttling, password reset, and TOTP 2FA
- Spatie Permission, Media Library, and Activity Log
- Database queues with short scheduled workers

## Local Setup

```bash
composer install
copy .env.example .env
php artisan key:generate
mysql -u root -p -e "CREATE DATABASE dmv_warriors CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE dmv_warriors_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`. The local seeded administrator is controlled by `CMS_ADMIN_*`; set a unique password before the first import. Never deploy the local default password.

In XAMPP, start MySQL before running Laravel. Both `dmv_warriors` and `dmv_warriors_test` are visible and manageable in phpMyAdmin.

## Common Commands

```bash
php artisan dmv:import-static
php artisan dmv:import-static --reset-content
php artisan cms:publish-scheduled
php artisan cms:purge-expired --dry-run
php artisan cms:backup
php artisan cms:monitor
php artisan test
npm run build
```

`legacy-static/` remains the migration reference and must be retained until content and visual parity are accepted.
The importer is non-destructive after its first successful run. Use `--reset-content` only when intentionally restoring legacy defaults.

## Documentation

- [Laravel CMS plan](LARAVEL_CMS_PLAN.md)
- [Deployment runbook](DEPLOYMENT.md)
- [Backup and restore](BACKUP_RESTORE.md)
- [CMS content coverage](CONTENT_COVERAGE.md)

The health endpoint is `/up`; the CMS is `/admin`.
