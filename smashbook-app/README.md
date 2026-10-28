# SmashBook App

Aplikasi operasional GOR badminton (Laravel 12 + MySQL). Dokumentasi lengkap, akun demo, setup, dan deployment ada di [`../README.md`](../README.md).

Quick start:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Tes:

```bash
php artisan test
```
