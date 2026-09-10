# Sistem Manajemen Jadwal Kegiatan Internal
### PT Inovindo Digital Media

Aplikasi web internal untuk mengelola jadwal petugas adzan/pembacaan kitab, briefing pagi/sore, dan alokasi ruangan — dengan rotasi tugas otomatis berbasis algoritma LRA (Least Recently Assigned).

---

## Stack

- **Laravel 13** (PHP 8.3) + **Livewire 4** + **Flux UI**
- **Tailwind CSS v4** + Alpine.js
- **MySQL** + Laravel Queue (database driver)
- **Laravel Fortify** (autentikasi headless)

---

## Cara Install

### 1. Clone & install dependencies

```bash
git clone <repo-url> giliran
cd giliran
composer install
npm install
```

### 2. Konfigurasi environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=giliran
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
```

### 3. Migrasi & seed database

```bash
php artisan migrate
php artisan db:seed
```

### 4. Build assets

```bash
npm run build
# atau untuk development:
npm run dev
```

### 5. Jalankan aplikasi

```bash
php artisan serve
```

Buka `http://localhost:8000`

---

## Akun Demo

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@giliran.test` | `password` |
| Tim | `tim@giliran.test` | `password` |

---

## Queue Worker

Notifikasi dikirim via Laravel Queue. Jalankan queue worker:

```bash
php artisan queue:work --queue=default
```

Untuk production, gunakan Supervisor atau Laravel Horizon.

---

## Scheduler (Notifikasi H-1)

Notifikasi H-1 dikirim otomatis setiap hari pukul 06:00 via Laravel Scheduler.

Tambahkan 1 baris cron di server:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Command yang dijadwalkan: `php artisan notifikasi:h1`

Untuk test manual:

```bash
php artisan notifikasi:h1
```

---

## Menjalankan Test

```bash
# Semua test
php artisan test --compact

# Filter test tertentu
php artisan test --compact --filter=LraSchedulerTest
php artisan test --compact --filter=GenerateJadwalTest
php artisan test --compact --filter=AutoSwapTest
```

> Test menggunakan database `giliran_test` (MySQL). Pastikan database tersebut sudah dibuat sebelum menjalankan test:
> ```sql
> CREATE DATABASE giliran_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> ```

---

## Struktur Portal

| Portal | URL | Role |
|--------|-----|------|
| Admin | `/admin/dashboard` | `admin` |
| Tim/Divisi | `/tim/beranda` | `tim` |

**Catatan:** Role `personil` sudah dihapus. Setiap anggota tim login menggunakan akun tim mereka.

---

## Algoritma LRA

Rotasi tugas menggunakan **Least Recently Assigned (frequency-based)**:
- Personil/ruangan dengan jumlah tugas historis paling sedikit diprioritaskan
- Counter di-update in-memory selama 1 batch generate (tidak query DB tiap tanggal)
- Tie-breaker: shuffle acak jika counter sama
- Service class: `app/Services/LraScheduler.php`

---

## Reset & Reseed

```bash
php artisan migrate:fresh --seed
```

Ini akan menghapus semua data dan mengisi ulang dengan data dummy dari seeder.
