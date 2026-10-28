<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\CashService;
use App\Services\PosService;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private User $owner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, UserSeeder::class, MembershipTypeSeeder::class, ProductSeeder::class]);

        $this->kasir = User::query()->where('email', 'dewi@smasharena.id')->firstOrFail();
        $this->owner = User::query()->where('email', 'owner@smasharena.id')->firstOrFail();
        $this->actingAs($this->kasir);

        app(CashService::class)->open($this->kasir, 'pagi', 300000);
        $this->product = Product::query()->where('sku', 'MNM-001')->firstOrFail();
    }

    public function test_checkout_reduces_stock_and_records_cash(): void
    {
        $beforeStock = $this->product->stock_qty;
        $beforeCash = (float) CashMovement::query()->where('type', 'in')->sum('amount');

        $result = app(PosService::class)->checkout(
            $this->kasir,
            [['product_id' => $this->product->id, 'qty' => 2]],
            null,
            ['cash_received' => 20000]
        );

        $sale = $result['sale'];

        $this->assertSame('paid', $sale->status);
        $this->assertSame(10000.0, (float) $sale->total_amount);
        $this->assertSame(10000.0, $result['change']);
        $this->assertSame($beforeStock - 2, $this->product->fresh()->stock_qty);
        $this->assertSame(1, Payment::query()->where('ref_type', 'pos')->where('ref_id', $sale->id)->where('method', 'cash')->count());
        $this->assertGreaterThan($beforeCash, (float) CashMovement::query()->where('type', 'in')->sum('amount'));
    }

    public function test_void_restores_stock_and_reverses_cash(): void
    {
        $pos = app(PosService::class);
        $result = $pos->checkout(
            $this->kasir,
            [['product_id' => $this->product->id, 'qty' => 2]],
            null,
            ['cash_received' => 20000]
        );

        $sale = $result['sale'];
        $stockAfterSale = $this->product->fresh()->stock_qty;
        $cashOutBefore = (float) CashMovement::query()->where('type', 'out')->sum('amount');

        $pos->void($sale, $this->kasir, 'Salah input item', '1234', true);

        $this->assertSame('void', $sale->fresh()->status);
        $this->assertSame($stockAfterSale + 2, $this->product->fresh()->stock_qty);
        $this->assertGreaterThan($cashOutBefore, (float) CashMovement::query()->where('type', 'out')->sum('amount'));
    }

    public function test_member_does_not_get_automatic_product_discount_at_pos(): void
    {
        $type = MembershipType::query()->where('name', 'Member Bulanan')->firstOrFail();
        $member = Member::create([
            'code' => 'MBR-POS-1',
            'name' => 'Member POS',
            'phone' => '081200001234',
            'member_type_id' => $type->id,
            'join_date' => now()->toDateString(),
            'status' => 'aktif',
            'deposit_balance' => 0,
        ]);

        MemberSubscription::create([
            'member_id' => $member->id,
            'membership_type_id' => $type->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'price' => $type->price,
            'paid_amount' => $type->price,
            'status' => 'aktif',
        ]);

        $result = app(PosService::class)->checkout(
            $this->kasir,
            [['product_id' => $this->product->id, 'qty' => 2]],
            $member->fresh(),
            ['cash_received' => 20000]
        );

        $this->assertSame(10000.0, (float) $result['sale']->total_amount);
        $this->assertSame(0.0, (float) $result['sale']->discount_amount);
    }

    public function test_checkout_rejects_quantity_above_stock(): void
    {
        $this->expectException(ValidationException::class);

        app(PosService::class)->checkout(
            $this->kasir,
            [['product_id' => $this->product->id, 'qty' => $this->product->stock_qty + 1]],
            null,
            ['cash_received' => 1000000]
        );
    }
}
