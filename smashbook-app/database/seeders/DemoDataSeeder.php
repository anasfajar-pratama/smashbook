<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Court;
use App\Models\Expense;
use App\Models\Member;
use App\Models\Product;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CashService;
use App\Services\PosService;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Booking::query()->whereDate('booking_date', now()->toDateString())->exists()) {
            $this->command?->warn('Data demo hari ini sudah ada — seeding operasional dilewati.');

            return;
        }

        $dewi = User::query()->where('email', 'dewi@smasharena.id')->firstOrFail();
        $admin = User::query()->where('email', 'admin@smasharena.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@smasharena.id')->firstOrFail();
        $rudi = User::query()->where('email', 'rudi@smasharena.id')->firstOrFail();

        $bookingService = app(BookingService::class);
        $cashService = app(CashService::class);
        $posService = app(PosService::class);
        $poService = app(PurchaseOrderService::class);

        $courts = Court::query()->get()->keyBy('code');
        $members = Member::query()->get()->keyBy('code');
        $products = Product::query()->get()->keyBy('sku');
        $today = now()->toDateString();

        $session = $cashService->activeSession($dewi) ?? $cashService->open($dewi, 'pagi', 300000);

        $createBooking = function (array $data, ?float $payment = null, string $method = 'cash', bool $complete = false) use ($bookingService, $dewi) {
            $booking = $bookingService->create($data, $dewi, false);

            if ($payment !== null && $payment > 0) {
                $bookingService->addPayment($booking, $method, $payment, $dewi);
            }

            if ($complete) {
                $bookingService->checkIn($booking);
                $bookingService->checkOut($booking->fresh());
            }

            return $booking->fresh();
        };

        $booking1 = $createBooking([
            'member_id' => $members['MBR-00003']->id,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '0812-3344-5566',
            'court_id' => $courts['C1']->id,
            'booking_date' => $today,
            'start_time' => '08:00',
            'duration_minutes' => 120,
            'source' => 'walkin',
            'notes' => 'Booking demo pagi.',
        ], null, 'cash', false);
        $bookingService->addPayment($booking1, 'cash', (float) $booking1->total_amount, $dewi);
        $bookingService->checkIn($booking1);
        $bookingService->checkOut($booking1->fresh());

        $booking2 = $createBooking([
            'customer_name' => 'Klub Merah Putih',
            'customer_phone' => '0813-2211-7788',
            'court_id' => $courts['C2']->id,
            'booking_date' => $today,
            'start_time' => '10:00',
            'duration_minutes' => 120,
            'source' => 'telepon',
        ]);
        $bookingService->addPayment($booking2, 'qris', (float) $booking2->total_amount, $dewi, 'QRIS-DEMO-001');
        $bookingService->checkIn($booking2);
        $bookingService->checkOut($booking2->fresh());

        $booking3 = $createBooking([
            'member_id' => $members['MBR-00012']->id,
            'customer_name' => 'Rina Wijaya',
            'customer_phone' => '0813-9988-1122',
            'court_id' => $courts['C3']->id,
            'booking_date' => $today,
            'start_time' => '13:00',
            'duration_minutes' => 120,
            'source' => 'wa',
        ], null, 'cash');
        $bookingService->addPayment($booking3, 'cash', (float) $booking3->total_amount, $dewi);

        $booking4 = $createBooking([
            'customer_name' => 'Hendra (walk-in)',
            'customer_phone' => '0812-3344-5566',
            'court_id' => $courts['C4']->id,
            'booking_date' => $today,
            'start_time' => '16:00',
            'duration_minutes' => 120,
            'source' => 'walkin',
        ], null, 'cash');
        $bookingService->addPayment($booking4, 'cash', (float) $booking4->total_amount, $dewi);

        $booking5 = $createBooking([
            'member_id' => $members['MBR-00031']->id,
            'customer_name' => 'Komunitas Smash',
            'customer_phone' => '0811-2233-8899',
            'court_id' => $courts['C1']->id,
            'booking_date' => $today,
            'start_time' => '18:00',
            'duration_minutes' => 120,
            'source' => 'wa',
            'notes' => 'DP 50%, sisa dibayar di tempat.',
        ], null, 'cash');
        $bookingService->addPayment($booking5, 'cash', round((float) $booking5->total_amount * 0.5), $dewi);

        $booking6 = $createBooking([
            'customer_name' => 'Deni Kurniawan',
            'customer_phone' => '0857-1122-3344',
            'court_id' => $courts['C2']->id,
            'booking_date' => $today,
            'start_time' => '19:00',
            'duration_minutes' => 120,
            'source' => 'telepon',
        ]);

        $bookingService->create([
            'customer_name' => 'Andi Pratama',
            'customer_phone' => '0821-7766-5544',
            'court_id' => $courts['C4']->id,
            'booking_date' => $today,
            'start_time' => '19:00',
            'duration_minutes' => 120,
            'source' => 'telepon',
            'notes' => 'Hold sementara 15 menit.',
        ], $dewi, true);

        $total = function (array $items) use ($products) {
            $subtotal = 0;
            foreach ($items as [$sku, $qty]) {
                $subtotal += (float) $products[$sku]->sell_price * $qty;
            }

            return $subtotal;
        };

        $sale1Items = [['MNM-001', 3], ['SHC-001', 1], ['SEW-001', 2]];
        $posService->checkout($dewi, array_map(fn ($row) => ['product_id' => $products[$row[0]]->id, 'qty' => $row[1]], $sale1Items), null, ['cash_received' => 100000]);

        $sale2Items = [['MNM-004', 2], ['MNM-006', 2]];
        $sale2Total = $total($sale2Items);
        $posService->checkout($dewi, array_map(fn ($row) => ['product_id' => $products[$row[0]]->id, 'qty' => $row[1]], $sale2Items), null, ['qris' => $sale2Total, 'cash_received' => 0, 'reference_no' => 'QRIS-DEMO-002']);

        $sale3Items = [['AKS-002', 1], ['MNM-002', 1]];
        $posService->checkout($dewi, array_map(fn ($row) => ['product_id' => $products[$row[0]]->id, 'qty' => $row[1]], $sale3Items), null, ['cash_received' => 20000]);

        $sale4Items = [['SHC-004', 1], ['MNM-001', 1]];
        $sale4Total = $total($sale4Items);
        $posService->checkout($dewi, array_map(fn ($row) => ['product_id' => $products[$row[0]]->id, 'qty' => $row[1]], $sale4Items), null, ['debit' => $sale4Total, 'cash_received' => 0, 'reference_no' => 'EDC-DEMO-003']);

        $memberBudi = Member::with('subscriptions.membershipType')->find($members['MBR-00003']->id);
        $sale5Items = [['MNM-001', 2], ['JSA-001', 1]];
        $sale5Total = $total($sale5Items);
        $posService->checkout($dewi, array_map(fn ($row) => ['product_id' => $products[$row[0]]->id, 'qty' => $row[1]], $sale5Items), $memberBudi, ['qris' => $sale5Total, 'cash_received' => 0, 'reference_no' => 'QRIS-DEMO-004']);

        $posService->checkout($dewi, [['product_id' => $products['SEW-001']->id, 'qty' => 1]], null, ['cash_received' => 20000], 0, null, null, $booking4->id);

        $cashService->recordCashOut(85000, 'kas_kecil', 'Beli gas + air galon dapur', null, null, $dewi);
        $cashService->recordCashOut(50000, 'pembelian', 'Shuttlecock darurat warung sebelah', null, null, $dewi);

        $yesterday = now()->subDay();
        $yesterdayCode = 'KAS-'.$yesterday->format('Ymd').'-MALAM';

        if (! CashSession::query()->where('code', $yesterdayCode)->exists()) {
            $closed = CashSession::create([
                'code' => $yesterdayCode,
                'shift' => 'malam',
                'cashier_id' => $rudi->id,
                'opened_at' => $yesterday->copy()->setTime(21, 0),
                'opening_balance' => 200000,
                'closed_at' => $yesterday->copy()->setTime(23, 0),
                'expected_balance' => 200000,
                'physical_balance' => 195000,
                'difference' => -5000,
                'status' => 'closed',
                'notes' => 'Kurang receh, ditoleransi.',
                'verified_by' => $admin->id,
            ]);

            CashMovement::create([
                'cash_session_id' => $closed->id,
                'type' => 'in',
                'category' => 'modal_awal',
                'amount' => 200000,
                'description' => 'Modal awal shift',
                'created_by' => $rudi->id,
                'created_at' => $yesterday->copy()->setTime(21, 0),
                'updated_at' => $yesterday->copy()->setTime(21, 0),
            ]);
        }

        $suppliers = Supplier::query()->get()->keyBy('name');

        $poService->create([
            'supplier_id' => $suppliers['UD Sumber Rejeki']->id,
            'supplier_name' => 'UD Sumber Rejeki',
            'request_date' => $today,
            'expected_date' => now()->addDay()->toDateString(),
            'payment_method' => 'transfer',
            'priority' => 'normal',
            'notes' => 'Belanja bulanan air minum dan shuttlecock lokal.',
            'items' => [
                ['item_type' => 'product', 'product_id' => $products['MNM-001']->id, 'description' => 'Air Mineral 600ml — 1 dus (24 botol)', 'qty' => 10, 'unit' => 'dus', 'unit_price' => 74400],
                ['item_type' => 'product', 'product_id' => $products['SHC-004']->id, 'description' => 'Shuttlecock Lokal — 1 pack (3 pcs)', 'qty' => 10, 'unit' => 'pack', 'unit_price' => 36000],
            ],
        ], $admin, false);

        $poService->create([
            'supplier_id' => $suppliers['CV Terang Elektrik']->id,
            'supplier_name' => 'CV Terang Elektrik',
            'request_date' => $today,
            'expected_date' => now()->addDays(2)->toDateString(),
            'payment_method' => 'transfer',
            'priority' => 'normal',
            'notes' => 'Lampu lapangan 3 sudah redup sejak minggu lalu.',
            'items' => [
                ['item_type' => 'expense', 'description' => 'Lampu LED sorot 200W (lap. 3)', 'expense_category' => 'perawatan', 'qty' => 2, 'unit' => 'pcs', 'unit_price' => 450000],
                ['item_type' => 'expense', 'description' => 'Jasa pemasangan & kabel', 'expense_category' => 'perawatan', 'qty' => 1, 'unit' => 'paket', 'unit_price' => 350000],
            ],
        ], $admin, false);

        $po3 = $poService->create([
            'supplier_id' => $suppliers['PT Sport Sarana']->id,
            'supplier_name' => 'PT Sport Sarana',
            'request_date' => $today,
            'expected_date' => now()->addDays(3)->toDateString(),
            'payment_method' => 'transfer',
            'priority' => 'normal',
            'items' => [
                ['item_type' => 'product', 'product_id' => $products['SHC-001']->id, 'description' => 'Shuttlecock Yonex AS-30', 'qty' => 24, 'unit' => 'tube', 'unit_price' => 32000],
            ],
        ], $admin, false);
        $poService->approve($po3, $owner, 'Disetujui untuk stok turnamen.');

        $po4 = $poService->create([
            'supplier_id' => $suppliers['UD Sumber Rejeki']->id,
            'supplier_name' => 'UD Sumber Rejeki',
            'request_date' => now()->subDays(2)->toDateString(),
            'expected_date' => now()->subDay()->toDateString(),
            'payment_method' => 'transfer',
            'priority' => 'urgent',
            'items' => [
                ['item_type' => 'product', 'product_id' => $products['MNM-006']->id, 'description' => 'Kopi sachet — restock', 'qty' => 24, 'unit' => 'sachet', 'unit_price' => 3800],
            ],
        ], $admin, false);
        $poService->receive($po4, [$po4->items->first()->id => 24], $admin, null);

        $expenses = [
            ['EXP-SEED-001', 'listrik', 'Tagihan PLN lapangan & kantor', 4800000, 10],
            ['EXP-SEED-002', 'gaji', 'Gaji 4 staff kebersihan', 4400000, 5],
            ['EXP-SEED-003', 'sewa', 'Sewa lahan & bangunan', 3500000, 3],
            ['EXP-SEED-004', 'perawatan', 'Ganti net 2 lapangan', 640000, 16],
            ['EXP-SEED-005', 'internet', 'Internet & CCTV bulanan', 450000, 14],
            ['EXP-SEED-006', 'kebersihan', 'Sabun, tisu, pewangi ruang ganti', 325000, 12],
        ];

        foreach ($expenses as [$code, $category, $description, $amount, $day]) {
            Expense::query()->updateOrCreate(['code' => $code], [
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'expense_date' => now()->startOfMonth()->addDays($day - 1)->toDateString(),
                'payment_method' => 'transfer',
                'created_by' => $admin->id,
            ]);
        }

        $recurring = [
            ['name' => 'Sewa lahan & bangunan', 'category' => 'sewa', 'amount' => 3500000, 'day_of_month' => 1],
            ['name' => 'Internet & CCTV', 'category' => 'internet', 'amount' => 450000, 'day_of_month' => 14],
            ['name' => 'Gaji staff kebersihan', 'category' => 'gaji', 'amount' => 4400000, 'day_of_month' => 5],
        ];

        foreach ($recurring as $item) {
            RecurringExpense::query()->updateOrCreate(['name' => $item['name']], $item + ['is_active' => true]);
        }
    }
}
