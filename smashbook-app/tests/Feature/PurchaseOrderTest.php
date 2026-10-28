<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CashService;
use App\Services\InventoryService;
use App\Services\PurchaseOrderService;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SupplierSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, UserSeeder::class, SupplierSeeder::class]);

        $this->admin = User::query()->where('email', 'admin@smasharena.id')->firstOrFail();
        $this->owner = User::query()->where('email', 'owner@smasharena.id')->firstOrFail();
        $this->service = app(PurchaseOrderService::class);
        $this->actingAs($this->admin);
    }

    private function product(int $stock = 0, float $cost = 1000): Product
    {
        $product = Product::create([
            'sku' => 'TEST-'.uniqid(),
            'name' => 'Produk Uji',
            'category' => 'Uji',
            'base_unit' => 'pcs',
            'conversion_qty' => 1,
            'cost_price' => $cost,
            'sell_price' => 2000,
            'stock_qty' => 0,
            'min_stock' => 0,
            'allow_negative' => false,
            'is_active' => true,
        ]);

        if ($stock > 0) {
            app(InventoryService::class)->openingStock($product, $stock, $cost, $this->admin);
        }

        return $product->fresh();
    }

    public function test_po_above_admin_threshold_waits_for_approval_then_can_be_received(): void
    {
        $product = $this->product();
        $supplier = Supplier::query()->firstOrFail();

        $po = $this->service->create([
            'supplier_id' => $supplier->id,
            'request_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'priority' => 'normal',
            'items' => [
                ['item_type' => 'product', 'product_id' => $product->id, 'description' => 'Produk Uji', 'qty' => 600, 'unit' => 'pcs', 'unit_price' => 1000],
            ],
        ], $this->admin);

        $this->assertSame('submitted', $po->status);
        $this->assertSame(600000.0, (float) $po->total_amount);

        $approved = $this->service->approve($po, $this->owner);
        $this->assertSame('approved', $approved->status);

        $item = $approved->items->first();
        $received = $this->service->receive($approved, [$item->id => 600], $this->admin);

        $this->assertSame('received', $received->status);
        $this->assertSame(600, $product->fresh()->stock_qty);
    }

    public function test_partial_receive_and_weighted_average_cost(): void
    {
        $product = $this->product(100, 1000);
        $supplier = Supplier::query()->firstOrFail();

        $po = $this->service->create([
            'supplier_id' => $supplier->id,
            'request_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'priority' => 'normal',
            'items' => [
                ['item_type' => 'product', 'product_id' => $product->id, 'description' => 'Tambah stok', 'qty' => 100, 'unit' => 'pcs', 'unit_price' => 2000],
            ],
        ], $this->admin);

        $this->assertSame('approved', $po->status);
        $item = $po->items->first();

        $partial = $this->service->receive($po, [$item->id => 40], $this->admin);
        $this->assertSame('approved', $partial->status);
        $this->assertSame(140, $product->fresh()->stock_qty);

        $full = $this->service->receive($partial->fresh(), [$item->id => 60], $this->admin);
        $this->assertSame('received', $full->status);
        $this->assertSame(200, $product->fresh()->stock_qty);
        $this->assertSame(1500.0, (float) $product->fresh()->cost_price);
    }

    public function test_cash_expense_po_creates_expense_and_cash_out(): void
    {
        $supplier = Supplier::query()->firstOrFail();
        app(CashService::class)->open($this->admin, 'sore', 300000);

        $po = $this->service->create([
            'supplier_id' => $supplier->id,
            'request_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'priority' => 'urgent',
            'items' => [
                ['item_type' => 'expense', 'description' => 'Jasa perbaikan lampu', 'expense_category' => 'perawatan', 'qty' => 1, 'unit' => 'paket', 'unit_price' => 100000],
            ],
        ], $this->admin);

        $this->assertSame('approved', $po->status);
        $item = $po->items->first();
        $cashOutBefore = (float) CashMovement::query()->where('type', 'out')->sum('amount');

        $this->service->receive($po, [$item->id => 1], $this->admin, 'receipts/nota-uji.jpg');

        $this->assertDatabaseHas('expenses', [
            'purchase_order_id' => $po->id,
            'category' => 'perawatan',
            'amount' => 100000,
        ]);
        $this->assertGreaterThan($cashOutBefore, (float) CashMovement::query()->where('type', 'out')->sum('amount'));
    }
}
