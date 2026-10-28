<?php

use App\Services\SettingService;
use Carbon\Carbon;

if (! function_exists('rupiah')) {
    function rupiah(int|float|null $amount): string
    {
        return 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
    }
}

if (! function_exists('angka')) {
    function angka(int|float|null $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals, ',', '.');
    }
}

if (! function_exists('tanggal')) {
    function tanggal(Carbon|string|null $date, string $format = 'j M Y'): string
    {
        if (! $date) {
            return '-';
        }

        return Carbon::parse($date)->locale('id')->translatedFormat($format);
    }
}

if (! function_exists('jam')) {
    function jam(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '-';
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingService::class)->get($key, $default);
    }
}

if (! function_exists('audit_label')) {
    function audit_label(string $action): string
    {
        return match ($action) {
            'booking.create' => 'Booking dibuat',
            'booking.payment' => 'Pembayaran booking',
            'booking.cancel' => 'Booking dibatalkan',
            'booking.checkin' => 'Check-in booking',
            'booking.checkout' => 'Check-out booking',
            'booking.noshow' => 'Booking no-show',
            'booking.recurring.stop' => 'Jadwal rutin dihentikan',
            'pos.checkout' => 'Transaksi POS',
            'pos.void' => 'Void transaksi',
            'po.create' => 'PO dibuat',
            'po.approve' => 'PO disetujui',
            'po.reject' => 'PO ditolak',
            'po.receive' => 'Penerimaan PO',
            'cash.open' => 'Shift kas dibuka',
            'cash.close' => 'Shift kas ditutup',
            'member.create' => 'Member baru',
            'member.renew' => 'Membership diperpanjang',
            'member.reset_noshow' => 'Sanksi no-show direset',
            'deposit.topup' => 'Top-up deposit',
            'stock.opname' => 'Stock opname',
            'expense.create' => 'Biaya operasional dicatat',
            'settings.update' => 'Pengaturan diubah',
            'user.create' => 'Pengguna ditambahkan',
            'user.update' => 'Pengguna diubah',
            'court.create' => 'Lapangan ditambahkan',
            'court.update' => 'Lapangan diubah',
            'court.toggle' => 'Status lapangan diubah',
            'price_rule.create' => 'Aturan tarif ditambahkan',
            'price_rule.delete' => 'Aturan tarif dihapus',
            'holiday.create' => 'Hari libur ditambahkan',
            'holiday.delete' => 'Hari libur dihapus',
            'supplier.create' => 'Supplier ditambahkan',
            default => ucwords(str_replace(['.', '_'], ' ', $action)),
        };
    }
}
