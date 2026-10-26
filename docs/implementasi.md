# Saran Implementasi Sistem Pengelolaan Lapangan Badminton

Dokumen ini melengkapi `design.md`: bagian **desain** menjawab "apa yang dibangun", dokumen ini
menjawab **"bagaimana membangunnya"** — pilihan teknologi, struktur kode, teknik menangani kasus
sulit, deployment, keamanan, dan strategi go-live.

---

## 1. Rekomendasi Teknologi

### Opsi A — Laravel 11 + MySQL 8 (REKOMENDASI UTAMA)

| Lapisan | Pilihan |
|---|---|
| Backend | PHP 8.3, Laravel 11 |
| Database | MySQL 8 / MariaDB 10.6 (innoDB, transaction penuh) |
| Frontend | Blade + Alpine.js + Tailwind CSS (build lokal) — atau Livewire untuk interaksi kompleks |
| Cetak | Cetak dari peramban ke printer thermal 58/80 mm (driver printer di komputer kasir) |
| Notifikasi | **Tanpa pihak ketiga** — sistem menyiapkan teks pesan, petugas mengirimnya manual via WhatsApp |
| Deployment | **Hosting Hostinger (shared hosting)** — paket dengan PHP 8.3, MySQL, cron, dan subdomain |

**Alasan memilih Laravel untuk kasus ini:**

1. **Ekonomis & mudah dirawat** — banyak pengembang PHP di Indonesia, biaya perawatan rendah, mudah dicari pengganti.
2. **Cocok untuk aplikasi transaksional internal** — Eloquent + DB transaction + `lockForUpdate()` langsung menjawab kebutuhan anti-double-booking dan konsistensi kas.
3. **Cocok untuk shared hosting** — tidak memerlukan Node, Redis, maupun worker permanen; tugas terjadwal dijalankan oleh cron Hostinger (batasannya dibahas di bagian 1.1).
4. **Ekosistem lengkap**: scheduler (pengingat jadwal, pelepasan penahanan slot, biaya rutin bulanan), policy (hak akses), notification, dan testing bawaan.
5. **Biaya operasional rendah** — tanpa sewa server bulanan dan tanpa perangkat keras tambahan di lokasi; cukup paket hosting Hostinger yang dibayar langsung oleh pengelola, dan aplikasi tetap dapat diakses dari mana saja.

### Opsi B — Next.js 15 + Prisma + PostgreSQL

Cocok bila owner menginginkan aplikasi berbasis cloud dengan PWA (tablet/HP) dan tim pengembang
berbasis JavaScript. Trade-off: biaya pengembangan lebih tinggi, kebutuhan pencetakan struk thermal
dari peramban lebih rumit (butuh layer lokal/RawBT), dan butuh hosting yang selalu online.

### Opsi C — Aplikasi siap pakai + kustomisasi

Bila targetnya operasional cepat dengan biaya awal rendah, bisa memakai platform POS/booking
siap pakai. Risiko: modul kas per shift, Quick PO, dan kartu stok biasanya **tidak** sesuai kebiasaan
GOR Indonesia (banyak yang tidak memisahkan tunai per shift), sehingga penyesuaian tetap diperlukan.

> **Keputusan yang disarankan:** **Opsi A (Laravel) dipasang pada shared hosting Hostinger** milik
> pengelola. Ini pilihan paling hemat dan paling sederhana untuk aplikasi internal dengan empat
> pengguna aktif. Naik ke VPS hanya bila kelak benar-benar dibutuhkan: jumlah pengguna bersamaan
> bertambah banyak, atau mode offline POS dipakai secara penuh.

### 1.1 Batasan Shared Hosting Hostinger & Cara Mengatasinya

| Batasan | Dampak | Cara mengatasi |
|---|---|---|
| Tidak ada worker/daemon permanen | Antrean tugas tidak berjalan dengan sendirinya | Karena tidak ada integrasi pihak ketiga, antrean nyaris tidak diperlukan — cukup `QUEUE_CONNECTION=sync`. Bila tetap dipakai (mis. email), jalankan `queue:work --stop-when-empty` dari cron tiap menit |
| Cron terbatas (umumnya interval 1 menit) | Tugas terjadwal tidak bisa per detik | Semua tugas dirancang per menit/harian: pelepasan penahanan slot per menit, pengingat harian, backup harian |
| Tidak tersedia layanan realtime (WebSocket) | Papan jadwal tidak berubah otomatis antar-layar | Papan jadwal memuat ulang saat dibuka + tombol muat ulang; validasi bentrok tetap di server, sehingga keamanan tidak bergantung pada realtime |
| Sumber daya CPU/memori dibatasi | Laporan berat bisa lambat | Batasi rentang laporan, tambahkan indeks, dan simpan ringkasan harian bila diperlukan |
| Backup otomatis hPanel belum tentu tersedia | Risiko kehilangan data | Pasang dump basis data terjadwal lewat cron, lalu Admin mengunduhnya ke komputer/NAS (tanpa layanan penyimpanan pihak ketiga) |
| Kuota basis data terbatas | Pertumbuhan data bertahun-tahun | Arsipkan transaksi lama per tahun bila diperlukan |

> Semua hal di atas sudah diperhitungkan pada desain sistem — tidak ada fitur yang mengharuskan
> adanya server sendiri.

---

## 2. Arsitektur Aplikasi

Terapkan pemisahan berlapis sederhana, tanpa over-engineering:

```
app/
├─ Http/Controllers/
│   ├─ BookingController.php        (HTTP tipis: validasi + panggil service)
│   ├─ PosController.php
│   ├─ CashSessionController.php
│   ├─ PurchaseOrderController.php
│   ├─ InventoryController.php
│   └─ ReportController.php
├─ Services/                        ← SEMUA logika bisnis di sini
│   ├─ BookingService.php           (checkAvailability, create, cancel, checkIn)
│   ├─ PricingService.php           (hitung tarif peak/off-peak per slot)
│   ├─ PosService.php               (checkout dalam 1 transaksi DB)
│   ├─ CashService.php              (open, movement, close, rekonsiliasi)
│   ├─ InventoryService.php         (movement, opname, weighted average)
│   ├─ PurchaseOrderService.php     (create, approve, receive parsial)
│   ├─ DocumentNumberService.php    (BK/TRX/KAS/PO/OPN)
│   └─ ReportService.php
├─ Models/                          (relasi Eloquent, tanpa logika berat)
├─ Policies/                        (BookingPolicy, PurchaseOrderPolicy, dll)
├─ Events & Listeners/
│   ├─ BookingConfirmed → kirim WA
│   ├─ ShiftClosed      → notifikasi owner bila ada selisih
│   └─ StockBelowMinimum → tampilkan alert
└─ Console/Commands/
    ├─ ReleaseExpiredHolds.php      (setiap menit)
    ├─ SendBookingReminders.php     (harian 09:00)
    ├─ GenerateRecurringBookings.php (harian 00:30)
    ├─ GenerateRecurringExpenses.php (tanggal 1 tiap bulan)
    └─ BackupDatabase.php           (harian 02:00)
```

**Prinsip:** controller tipis, service mengandung aturan bisnis, model hanya relasi & scope.
Ini membuat logika booking/kas bisa diuji tanpa HTTP dan dipakai ulang oleh command/scheduler.

---

## 3. Teknik Menangani Kasus Sulit

### 3.1 Anti double-booking (paling kritis)

**Jangan** hanya melakukan `SELECT` lalu `INSERT` — dua kasir bisa lolos bersamaan. Terapkan:

```php
DB::transaction(function () use ($data) {
    // 1) kunci slot yang dituju (urutkan agar tidak deadlock)
    $slots = CourtSlot::where('court_id', $data['court_id'])
        ->where('slot_date', $data['date'])
        ->whereIn('slot_hour', $hours)
        ->lockForUpdate()
        ->get();

    // 2) pastikan tidak ada yang terisi (bukan hold kedaluwarsa)
    if ($slots->whereNotNull('booking_id')
              ->where('status', '!=', 'expired')->isNotEmpty()) {
        throw new SlotTakenException();
    }

    // 3) isi slot + simpan booking + hitung harga
    ...
});
```

Pengaman terakhir ada di level database: `UNIQUE (court_id, slot_date, slot_hour)` pada `court_slots`.
Artinya, **secara fisik mustahil** ada dua booking pada lapangan, tanggal, dan jam yang sama.
Tangani `QueryException` unique-violation sebagai pesan ramah: *"Slot baru saja diambil operator lain, silakan pilih jam lain."*

### 3.2 Perhitungan tarif campuran (mid-slot price change)

Harga tidak dihitung dari durasi total, tapi **per slot per jam** menggunakan `court_price_rules`
(hari kerja/weekend/hari libur × rentang jam). Booking 15:00–17:00 pada hari kerja = 1 jam off-peak
+ 1 jam prime. Simpan hasil per-slot di `booking_slots` supaya laporan tetap akurat
walau tarif master berubah di kemudian hari (snapshot).

### 3.3 Konsistensi kas (tidak ada uang tunai tanpa jejak)

Bungkus semua operasi uang dalam satu transaksi DB dan **paksa lewat CashService**:

```php
public function recordCashIn(int $amount, string $refType, int $refId, string $desc) {
    $session = CashSession::where('cashier_id', auth()->id())
                          ->where('status', 'open')->lockForUpdate()->first();
    if (!$session) throw new NoOpenCashSessionException(); // blokir transaksi tunai
    // insert cash_movements + update saldo sesi
}
```

- Pembayaran non-tunai **tetap** dicatat di `payments` (untuk rekonsiliasi QRIS/transfer) tetapi tidak mengubah kas fisik.
- Idempotency: simpan `ref_type + ref_id` unik per movement agar retry/klik ganda tidak menggandakan setoran.

### 3.4 Konsistensi stok

Semua perubahan stok **hanya** lewat `InventoryService::move()` yang dalam transaksi yang sama:
1. insert `inventory_movements` (dengan `balance_after`),
2. update `products.stock_qty` (cache) dengan `lockForUpdate()`,
3. hitung ulang **weighted average cost** bila stok masuk:

```
avg_baru = (stok_lama × avg_lama + qty_masuk × harga_beli) / (stok_lama + qty_masuk)
```

Stok minus dicegah dengan validasi `qty >= available` saat checkout, bukan saat item masuk keranjang
(keranjang hanya menampilkan sisa; dua kasir bisa menjual barang terakhir bersamaan).

### 3.5 Penomoran dokumen tanpa duplikat

Gunakan tabel `sequences (prefix, period, last_number)` dengan `lockForUpdate()`, atau
`INSERT ... ON DUPLICATE KEY UPDATE last_number = last_number + 1` lalu baca nilai. Hindari
`count(*) + 1` — selalu bentrok saat dua transaksi bersamaan.

### 3.6 Job terjadwal (scheduler)

| Command | Frekuensi | Fungsi |
|---|---|---|
| `ReleaseExpiredHolds` | tiap menit | Lepas booking `hold` yang lewat 15 menit |
| `SendBookingReminders` | harian 09:00 | Menyusun daftar pengingat jadwal besok di dashboard (petugas mengirim manual via WhatsApp) |
| `GenerateRecurringBookings` | harian 00:30 | Buat booking otomatis dari jadwal rutin komunitas |
| `GenerateRecurringExpenses` | tgl 1 tiap bulan | Draft expense rutin (sewa, internet, gaji) untuk dikonfirmasi |
| `BackupDatabase` | harian 02:00 | Dump DB terenkripsi + rotasi 30 hari |
| `CheckMembershipExpiry` | harian 08:00 | Tandai expired & kirim notifikasi H-7 |

Di Hostinger seluruh command di atas dipanggil melalui **cron hPanel** — satu entri cron yang
menjalankan `php artisan schedule:run` tiap menit — bukan daemon yang berjalan terus-menerus.
Karena itu, pastikan penjadwalan benar-benar aktif sebelum go-live: bila cron mati, penahanan slot
tidak akan pernah dilepas dan pengingat jadwal tidak terkirim.

### 3.7 Mode offline POS (disarankan dikerjakan lebih awal)

Karena seluruh sistem berjalan di hosting dan bukan di server lokal, **seluruh transaksi bergantung
pada koneksi internet**. Mode offline POS menjadi pengaman utama saat internet GOR terganggu, dan
karena itu layak diprioritaskan lebih awal dibanding fitur tambahan lain. Pendekatan bertahap dan aman:
1. POS menyimpan transaksi ke **IndexedDB** saat API tidak tersedia + tandai `pending_sync`.
2. Beri **nomor transaksi offline** dengan prefix kasir (mis. `TRX-OFF-K1-0007`) agar tidak bentrok saat sinkron.
3. Saat online, kirim batch ke endpoint idempotent (`client_uuid` unik per transaksi) — server mengabaikan duplikat.
4. Stok di sisi offline dihitung lokal dengan cache terakhir; konflik stok diselesaikan saat sinkron dan dilaporkan sebagai selisih untuk opname.

Mulai dari langkah 1–3 saja; hindari sinkronisasi dua arah yang kompleks di fase awal.

---

## 4. Model Keamanan

| Area | Implementasi |
|---|---|
| Autentikasi | Login per user, **tanpa akun bersama per shift** (wajib, agar selisih kas bisa dipertanggungjawabkan) |
| Password | Hash argon2id/bcrypt, minimal 8 karakter, reset via email/WA oleh admin |
| PIN approval | 4–6 digit per user untuk void, diskon besar, override tarif, tutup shift berselisih |
| Otorisasi | Laravel Policies + middleware role; cek di server, bukan sembunyikan tombol saja |
| Audit | `audit_logs` mencatat before/after JSON + alasan; kasir tidak bisa menghapus |
| Sesi | Auto-logout idle 15 menit di POS; refresh token terbatas |
| Jaringan | HTTPS wajib (SSL dari Hostinger); basis data tidak dapat diakses publik; tidak ada halaman pemesanan publik sehingga permukaan serangan lebih kecil |
| Backup | Harian ke disk terpisah + mingguan offsite; uji restore tiap bulan (backup yang tidak pernah diuji = tidak ada backup) |
| Data pribadi | Nomor HP member & pelanggan hanya bisa dilihat peran berwenang; ekspor dibatasi & tercatat |

---

## 5. Perangkat Pendukung (Tanpa Integrasi Pihak Ketiga)

**Printer thermal (58/80mm).** Karena aplikasi berjalan di hosting dan bukan di komputer kasir, cara
paling andal adalah **mencetak dari peramban**: struk disusun sebagai halaman HTML khusus berukuran
kertas 58/80 mm, lalu dikirim ke driver printer thermal yang terpasang di komputer kasir. Cetak
mentah ESC/POS langsung dari aplikasi web memerlukan penghubung kecil di komputer kasir (aplikasi
desktop ringan yang menerima perintah cetak) — baru dipertimbangkan bila kecepatan cetak menjadi
masalah nyata.

**Scanner barcode/QR.** Mode "keyboard wedge" (paling mudah, tanpa SDK): fokus otomatis ke kolom
pencarian, scanner memasukkan kode + Enter. Untuk kartu member, kode QR berisi `MBR-00042` →
langsung memuat profil member saat check-in.

**WhatsApp (manual, tanpa layanan pihak ketiga).** Tidak ada API maupun langganan: sistem hanya
menyiapkan **teks pesan siap kirim** (konfirmasi booking, pengingat H-1, member akan berakhir,
ringkasan struk) lengkap dengan tombol "salin teks" dan tautan klik-untuk-chat ke nomor pelanggan.
Petugas mengirimnya dari WhatsApp di komputer atau teleponnya sendiri. Konsekuensinya, pengiriman
bergantung pada petugas — karena itu daftar tugas pengingat harian harus tampil di dashboard agar
tidak ada yang terlewat.

**QRIS &amp; mesin EDC (manual).** Pelanggan memindai QRIS statis milik GOR atau membayar melalui mesin
EDC yang sudah ada; kasir memastikan dana masuk, lalu mencatat transaksinya di sistem beserta nomor
referensi. Tidak ada integrasi API, tidak ada komisi/bagi hasil penyedia, dan tidak ada tagihan
langganan — konsekuensinya rekonsiliasi tetap perlu ketelitian kasir saat tutup shift.

---

## 6. Strategi Pengujian

Prioritas pengujian pada titik yang berhubungan dengan uang dan jadwal:

| Jenis | Yang diuji |
|---|---|
| **Unit** | PricingService (tarif campuran, weekend, hari libur, diskon member), weighted average, kalkulasi selisih kas |
| **Feature/integration** | Booking bentrok (2 request paralel → tepat 1 sukses), checkout POS (stok turun + kas naik + HPP benar), tutup shift dengan selisih, penerimaan PO parsial |
| **Concurrency** | Simulasi 20 request booking slot sama bersamaan (assert hanya 1 booking tercipta) |
| **UAT (peran)** | Kasir, Operator, Admin, Owner mengeksekusi skenario harian masing-masing di staging dengan data contoh |
| **Regresi keuangan** | Setiap rilis: jalankan skenario "hari penuh" (buka shift → 30 transaksi → tutup shift) dan bandingkan total |

Alat: PHPUnit/Pest untuk backend, seeder data demo (lapangan, produk, member contoh) agar QA dan
pelatihan tidak memakai data produksi.

---

## 7. Deployment & Infrastruktur

### Skenario: aplikasi pada hosting Hostinger

```
┌──── Hostinger (hPanel) ────────────────────────┐
│  public_html/  ← aplikasi (PHP 8.3 + MySQL)    │
│  ├─ SSL otomatis (HTTPS)                       │
│  ├─ Cron tiap menit → schedule:run             │
│  ├─ Cron harian    → backup DB + unduh offsite │
│  └─ Email notifikasi                           │
└───────────────┬────────────────────────────────┘
                │ internet
   ┌────────────┴─────────────┬───────────────────┐
┌──▼───────────┐  ┌──────────▼─────────┐  ┌──────▼──────────┐
│ PC/tablet    │  │ PC/tablet owner    │  │ HP (WhatsApp)   │
│ kasir +      │  │ (laporan, approval)│  │ notifikasi      │
│ printer 58/80│  └────────────────────┘  └─────────────────┘
└──────────────┘
```

Langkah pemasangan: unggah aplikasi ke `public_html` dengan document root diarahkan ke folder
`public`, buat basis data MySQL, isi berkas `.env` (kredensial basis data + URL aplikasi), jalankan
migrasi & seeder melalui menu terminal hPanel, aktifkan SSL, pasang dua entri cron (`schedule:run`
tiap menit dan pemroses antrean), lalu verifikasi bahwa backup benar-benar terunduh.

Yang wajib diperhatikan pada skenario ini: **kualitas internet di GOR menjadi penentu utama**.
Sediakan hotspot seluler sebagai jalur cadangan, jalankan prosedur pencatatan kertas bila internet
putus total, dan jangan hanya mengandalkan fitur backup bawaan paket — pastikan salinan cadangan
tersimpan juga di luar Hostinger.

### Checklist go-live

- [ ] Migrasi data awal: daftar lapangan + tarif, daftar produk + stok awal (via opname), daftar member aktif, saldo deposit bila ada.
- [ ] Uji cetak struk di printer yang benar-benar dipakai (bukan printer lain).
- [ ] Pastikan cron Hostinger berjalan: periksa log tugas terjadwal dan terbuktinya pelepasan penahanan slot.
- [ ] Uji pemulihan dari berkas backup yang benar-benar terunduh — bukan hanya melihat bahwa backup terbentuk.
- [ ] Uji alur penuh: buka shift → booking → POS → tutup shift → rekap harian.
- [ ] Pelatihan per peran (kasir, operator, admin/owner) dengan data demo.
- [ ] Paralel run: catat di sistem **dan** buku selama beberapa sesi kerja, lalu bandingkan selisihnya sebelum buku ditinggalkan.
- [ ] Tetapkan SOP: siapa buka/tutup shift, siapa approve PO, jam backup, siapa boleh void.
- [ ] Simpan kontak pendukung & prosedur bila sistem mati (formulir cadangan kertas untuk 1 hari operasional).

---

## 8. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Operator mengeluh sistem "lebih ribet" | Adopsi gagal, kembali ke buku | Alur ≤ 3 klik, latih dengan skenario nyata, sediakan mode cepat walk-in, tampilkan manfaat (diskon & jadwal jelas) |
| Double booking tetap terjadi | Konflik di lapangan | Tiga lapis pertahanan (slot unik + row lock + validasi server) & uji konkurensi |
| Selisih kas tidak tertelusuri | Kecurigaan internal | Wajib sesi kas per shift, satu user = satu shift, audit log |
| Stok fisik ≠ sistem | Kehabisan barang saat ramai | Opname bulanan, alert minimum, setiap perubahan lewat movement |
| **Internet putus (risiko utama)** | Kasir dan pencatatan booking mandek karena aplikasi diakses sepenuhnya online | Hotspot seluler sebagai jalur cadangan; prioritaskan mode offline POS; sediakan formulir kertas + prosedur input ulang |
| Batasan paket shared hosting | Tugas terjadwal atau antrean berhenti tanpa disadari | Uji cron & queue sebelum go-live dan tambahkan pengingat bila tugas terjadwal tidak berjalan |
| Data hilang | Fatal | Backup harian + offsite + uji restore bulanan |
| Threshold approval terlalu ketat | Operasional lambat | Buat threshold & auto-approve pembelian tunai kecil dengan foto nota, evaluasi setelah 1 bulan pakai |
| Harga/tarif berubah tanpa jejak | Laporan historis berubah | Snapshot tarif & HPP pada baris transaksi |

---

## 9. Estimasi Biaya (indikatif, perlu penyesuaian)

**Biaya implementasi** dirinci pada `surat-penawaran.html`.
**Biaya operasional berkala** yang perlu dianggarkan:

| Komponen | Perkiraan/bulan | Catatan |
|---|---|---|
| Paket hosting & domain Hostinger | Dibayar langsung ke Hostinger | Akun milik pengelola; paket tahunan umumnya sudah mencakup domain + SSL |
| Pemeliharaan & support | Sesuai penawaran | Meliputi pemantauan, verifikasi cadangan, perbaikan gangguan |
| Printer thermal | Rp 350.000 – 1.200.000 (sekali) | 58mm / 80mm |
| Tablet kasir (opsional) | Rp 2.500.000 – 3.500.000 (sekali) | Hanya bila ingin POS layar sentuh; PC/laptop yang sudah ada tetap dapat dipakai |

---

## 10. Rencana Kerja Bertahap

| Fase | Aktivitas | Keluaran yang bisa diuji |
|---|---|---|
| F0 | Setup repo & pengujian dasar, pemasangan aplikasi pada hosting Hostinger (domain, SSL, basis data, cron, backup), auth, RBAC, master lapangan/tarif, penomoran dokumen | Login 4 peran, master data, nomor dokumen otomatis |
| F1 | Jadwal, booking, pricing, member, membership, check-in, reminder WA | Booking walk-in & di muka tanpa double booking, member tampil dengan diskon |
| F2 | POS, struk, multi-bayar, sesi kas, tutup shift, rekap harian | Satu hari penuh operasional tercatat & kas seimbang |
| F3 | Kartu stok, opname, Quick PO, approval, opEx, alert minimum | Siklus beli → stok naik → jual → stok turun terbukti |
| F4 | Dashboard KPI, laporan, occupancy, daftar pengingat harian, audit log | Owner bisa mengambil keputusan dari data |
| F5 | Migrasi data, pelatihan, paralel run, tuning, serah terima + dokumentasi SOP | Go-live |

Yang **tidak** dikerjakan di fase 1 (agar tidak melebar): QRIS otomatis, mode offline POS, FIFO,
multi-venue, payroll/akuntansi penuh. Halaman pemesanan publik juga tidak dikerjakan sama sekali —
booking hanya melalui telepon, WhatsApp, dan kunjungan langsung.

---

## 11. Definisi Selesai (Definition of Done)

Sebuah modul dinyatakan selesai bila:

1. Fungsi berjalan sesuai `design.md` termasuk aturan bisnis & validasinya.
2. Ada test otomatis untuk jalur kritis (uang, jadwal, stok) dan lulus.
3. Hak akses antar peran sudah diuji (kasir tidak bisa melihat HPP, operator tidak bisa void).
4. Tercatat di audit log untuk aksi sensitif.
5. Sudah diuji dengan perangkat nyata (printer/scanner) bila relevan.
6. Dokumentasi singkat (cara pakai per peran) tersedia untuk pelatihan.
