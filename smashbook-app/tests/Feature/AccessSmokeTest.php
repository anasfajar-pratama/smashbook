<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Member;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
    }

    public function test_owner_can_open_all_major_pages(): void
    {
        $owner = User::query()->where('email', 'owner@smasharena.id')->firstOrFail();
        $this->actingAs($owner);

        $booking = Booking::query()->firstOrFail();
        $member = Member::query()->firstOrFail();
        $product = Product::query()->firstOrFail();
        $sale = PosSale::query()->firstOrFail();
        $submittedPo = PurchaseOrder::query()->where('status', 'submitted')->first();
        $approvedPo = PurchaseOrder::query()->where('status', 'approved')->first();

        $urls = [
            route('dashboard'),
            route('bookings.index'),
            route('bookings.index', ['tab' => 'list']),
            route('bookings.index', ['tab' => 'recurring']),
            route('bookings.index', ['tab' => 'policy']),
            route('bookings.proof', $booking),
            route('courts.index'),
            route('members.index'),
            route('members.index', ['detail' => $member->id]),
            route('pos.index'),
            route('pos.history'),
            route('pos.receipt', $sale),
            route('cash.index'),
            route('cash.index', ['tab' => 'rekap']),
            route('cash.index', ['tab' => 'riwayat']),
            route('cash.index', ['tab' => 'setoran']),
            route('inventory.index'),
            route('inventory.index', ['tab' => 'masuk']),
            route('inventory.index', ['tab' => 'keluar']),
            route('inventory.index', ['tab' => 'opname']),
            route('inventory.index', ['card' => $product->id]),
            route('purchase-orders.index'),
            route('purchase-orders.index', ['tab' => 'list']),
            route('purchase-orders.index', ['tab' => 'opex']),
            route('purchase-orders.index', ['tab' => 'supplier']),
            route('reports.index'),
            route('reports.index', ['tab' => 'okupansi']),
            route('reports.index', ['tab' => 'kas']),
            route('reports.index', ['tab' => 'stok']),
            route('reports.index', ['tab' => 'member']),
            route('reports.index', ['tab' => 'labarugi']),
            route('admin.users'),
            route('admin.settings'),
            route('admin.audit'),
        ];

        if ($submittedPo) {
            $urls[] = route('purchase-orders.index', ['tab' => 'list', 'approve' => $submittedPo->id]);
        }

        if ($approvedPo) {
            $urls[] = route('purchase-orders.index', ['tab' => 'list', 'receive' => $approvedPo->id]);
        }

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_operator_and_kasir_are_restricted(): void
    {
        $operator = User::query()->where('email', 'operator@smasharena.id')->firstOrFail();
        $kasir = User::query()->where('email', 'dewi@smasharena.id')->firstOrFail();

        $this->actingAs($operator)->get(route('purchase-orders.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('pos.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('members.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('dashboard'))->assertOk()->assertDontSee('Pendapatan hari ini');

        $this->actingAs($kasir)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($kasir)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('pos.index'))->assertOk();

        $admin = User::query()->where('email', 'admin@smasharena.id')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.audit'))->assertOk();
        $this->actingAs($admin)->post(route('courts.price-rules.store'), [])->assertForbidden();
        $this->actingAs($admin)->post(route('courts.settings.update'), [])->assertForbidden();
    }
}
