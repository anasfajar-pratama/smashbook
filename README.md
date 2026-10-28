# SmashBook

Sistem operasional GOR badminton: **member, booking lapangan, POS, kas per shift, Quick PO, dan stock barang** dalam satu alur data yang saling terhubung.

Repo ini berisi:

| Lokasi | Isi |
|---|---|
| `docs/design.md` | Desain bisnis & model data |
| `docs/implementasi.md` | Rekomendasi teknologi & catatan implementasi |
| `prototype/` | Prototipe HTML statis (referensi UI) |
| `smashbook-app/` | **Aplikasi Laravel 12 + MySQL yang siap dijalankan** |
| `screenshots/` | Screenshot prototipe & aplikasi |

## Status

Aplikasi sudah berjalan penuh untuk enam modul inti, dengan **23 tes otomatis** pada jalur kritis (jadwal, uang, stok, approval) dan seed data demo siap pakai.

> Catatan versi: dokumen awal merekomendasikan Laravel 11. Laravel 11 sudah terkena *security advisories* dan diblokir Composer, sehingga implementasi memakai **Laravel 12** (PHP 8.3 + MySQL 8). Struktur, pola service, dan perilaku bisnisnya tetap sama.

## Akun demo

Password semua akun: `smashbook123` · PIN approval: `1234`

| Email | Peran |
|---|---|
| `owner@smasharena.id` | Owner |
| `admin@smasharena.id` | Admin |
| `dewi@smasharena.id` | Kasir (punya shift aktif di data demo) |
| `rudi@smasharena.id` | Kasir |
| `operator@smasharena.id` | Operator Lapangan |

Ganti password dan PIN sebelum dipakai di lingkungan nyata.

## Menjalankan di mesin ini (Windows + Laragon)

PHP, Composer, dan MySQL Laragon sudah ditambahkan ke **PATH user**. Setelah membuka PowerShell baru, perintah `php` dan `composer` langsung tersedia.

Prasyarat: PHP 8.3, Composer, MySQL 8 (semua tersedia di `C:\laragon`).

```powershell
# 1. Buat database (sekali saja)
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS smashbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Siapkan aplikasi
cd D:\smashbook\smashbook-app
composer install
copy .env.example .env
php artisan key:generate
# sesuaikan DB_* dan DB_DUMP_BINARY di .env

# 3. Migrasi + data demo
php artisan migrate --seed
php artisan storage:link

# 4. Jalankan
php artisan serve
```

Buka `http://127.0.0.1:8000`.

Jika PowerShell lama masih belum mengenal `php`, tutup dan buka ulang terminal. Alternatif tanpa restart:

```powershell
cd D:\smashbook\smashbook-app
.\serve.bat
```

`serve.bat` memakai path PHP Laragon langsung, jadi tidak bergantung pada PATH terminal.

Tidak perlu Node/npm — CSS dan JavaScript disajikan langsung dari `public/assets` (tanpa build step).

## Tes

Tes memakai database terpisah `smashbook_test` (sudah dibuat pada setup di atas; jika belum: `CREATE DATABASE smashbook_test ...`).

```powershell
C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe artisan test
```

Cakupan:
- harga peak/off-peak/weekend/hari libur & diskon member
- booking bentrok & hold kedaluwarsa
- checkout POS: stok turun, kas naik, kembalian
- void: stok kembali & kas keluar
- tutup shift: expected, selisih, verifikasi Owner
- PO: threshold approval, penerimaan parsial, weighted average, expense tunai
- RBAC & smoke test semua halaman utama

## Alur integrasi yang sudah berjalan

1. **Booking → kas**: pembayaran tunai booking otomatis masuk `cash_movements` shift aktif; QRIS/transfer/debit tetap tercatat di `payments` untuk rekonsiliasi.
2. **Booking → slot**: setiap booking mengisi `court_slots` dengan unique `(court_id, slot_date, slot_time)`; overlap ditolak server. Hold otomatis dilepas oleh scheduler.
3. **POS → stok + kas**: stok berkurang lewat `inventory_movements`, HPP snapshot disimpan di item, pembayaran tunai masuk kas. Diskon member **tidak** otomatis di POS — diskon member hanya untuk sewa lapangan; POS memakai diskon manual (PIN Owner di atas batas).
4. **POS void → stok + kas**: stok dikembalikan, kas tunai dikurangi, wajib PIN Owner, tercatat di audit log.
5. **PO → stok/expense + kas**: item produk menambah stok dan HPP rata-rata; item biaya membuat `expenses`; pembayaran tunai mengurangi kas saat penerimaan.
6. **Opname → adjustment**: semua koreksi stok lewat movement, bukan edit kolom stok.
7. **Membership → deposit**: top-up/usage/refund tercatat di `deposits`; deposit bisa dipakai di booking dan POS.
8. **Semua aksi sensitif → audit log**: void, diskon besar, pembatalan, tutup shift berselisih, approval PO, perubahan pengaturan.

## Peran & akses

- **Owner**: semua modul, pengaturan, pengguna, audit, approval di atas threshold.
- **Admin**: operasional, PO, stok, laporan, approval sampai threshold; tanpa pengaturan tarif global, pengaturan sistem, dan manajemen pengguna.
- **Kasir**: booking, POS, kas shift sendiri, member.
- **Operator Lapangan**: jadwal, booking walk-in, check-in/out; tanpa POS, laporan, PO, atau harga beli.

## Scheduler (cron)

Semua tugas terdaftar di `routes/console.php`:

| Command | Jadwal | Fungsi |
|---|---|---|
| `smashbook:release-holds` | tiap menit | Lepas hold booking kedaluwarsa |
| `smashbook:recurring-bookings` | 00:30 | Buat booking dari jadwal rutin |
| `smashbook:recurring-expenses` | tgl 1, 01:00 | Draft PO dari biaya rutin |
| `smashbook:check-membership` | 08:00 | Tandai membership expired |
| `smashbook:backup` | 02:00 | Dump MySQL + rotasi 30 hari |

Di Hostinger, satu cron cukup:

```
* * * * * cd /home/USER/domains/DOMAIN/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Backup ditulis ke `storage/app/private/backups` (juga dapat diunduh lewat panel hosting/SSH). Untuk produksi, pastikan `DB_DUMP_BINARY` benar atau `mysqldump` tersedia di `PATH`.

## Deployment Hostinger (ringkas)

1. Buat database MySQL + user di hPanel.
2. Upload project; arahkan document root ke folder `public`.
3. `composer install --no-dev --optimize-autoloader`, salin `.env`, isi `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain`, kredensial DB.
4. `php artisan key:generate`, `php artisan migrate --force`, `php artisan storage:link`.
5. Aktifkan SSL, pasang cron `schedule:run`, dan verifikasi backup benar-benar terbentuk.
6. Ganti semua password/PIN demo, lalu uji alur: buka shift → booking → POS → tutup shift → rekap.
7. Simpan salinan backup di luar hosting (komputer/NAS) secara berkala.

## Batasan yang belum dikerjakan

- Mode offline POS (IndexedDB + sinkronisasi idempoten) — prioritas fase berikutnya.
- Promo jam sepi sebagai tabel terpisah (saat ini bisa dimodelkan lewat aturan tarif off-peak).
- Template item favorit PO & perbandingan harga antar-supplier.
- Multi-venue di UI (kolom `venue_id` belum ditambahkan; model bisnis single venue).
- Pengiriman WhatsApp otomatis (sistem menyiapkan teks & tombol salin, tetap manual sesuai desain).
- Backup offsite otomatis dan uji restore terjadwal.

## Keamanan

- Password di-hash bcrypt; PIN approval di-hash.
- RBAC dicek di server (middleware + aturan controller), bukan hanya menyembunyikan tombol.
- Audit log menyimpan before/after JSON + alasan untuk aksi sensitif.
- Semua transaksi uang/stok dibungkus DB transaction; penomoran dokumen memakai sequence + row lock.
- Jangan commit `.env`; gunakan `.env.example` sebagai acuan.
