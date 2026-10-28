<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CashService;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, UserSeeder::class]);

        $this->kasir = User::query()->where('email', 'dewi@smasharena.id')->firstOrFail();
        $this->owner = User::query()->where('email', 'owner@smasharena.id')->firstOrFail();
        $this->actingAs($this->kasir);
    }

    public function test_expected_balance_and_close_with_zero_difference(): void
    {
        $cash = app(CashService::class);
        $session = $cash->open($this->kasir, 'pagi', 300000);

        $cash->recordCashIn(100000, 'koreksi', 'Kas masuk uji');
        $cash->recordCashOut(20000, 'kas_kecil', 'Kas keluar uji');

        $this->assertSame(380000.0, $session->fresh()->expectedBalance());

        $closed = $cash->close($session->fresh(), 380000, 'Sesuai');

        $this->assertSame('closed', $closed->status);
        $this->assertSame(380000.0, (float) $closed->expected_balance);
        $this->assertSame(0.0, (float) $closed->difference);
    }

    public function test_large_difference_requires_note_and_owner_verification(): void
    {
        $cash = app(CashService::class);
        $session = $cash->open($this->kasir, 'sore', 100000);

        try {
            $cash->close($session, 50000);
            $this->fail('Close seharusnya gagal tanpa catatan dan verifikasi owner.');
        } catch (ValidationException) {
            $this->assertSame('open', $session->fresh()->status);
        }

        $closed = $cash->close($session->fresh(), 50000, 'Uang hilang, sudah ditelusuri', $this->owner);

        $this->assertSame('closed', $closed->status);
        $this->assertSame(-50000.0, (float) $closed->difference);
        $this->assertSame($this->owner->id, $closed->verified_by);
    }

    public function test_one_cashier_cannot_open_two_sessions(): void
    {
        $cash = app(CashService::class);
        $cash->open($this->kasir, 'pagi', 100000);

        $this->expectException(ValidationException::class);
        $cash->open($this->kasir, 'sore', 100000);
    }
}
