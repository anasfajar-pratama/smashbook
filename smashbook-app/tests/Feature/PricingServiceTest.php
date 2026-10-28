<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Holiday;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\MemberSubscription;
use App\Services\PricingService;
use Carbon\Carbon;
use Database\Seeders\CourtSeeder;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, UserSeeder::class, MembershipTypeSeeder::class, CourtSeeder::class]);
    }

    public function test_mixed_peak_and_off_peak_are_priced_per_slot(): void
    {
        $court = Court::query()->where('code', 'C1')->firstOrFail();
        $quote = app(PricingService::class)->quote($court, Carbon::parse('2026-10-26'), '15:00', 120);

        $this->assertSame(160000.0, (float) $quote['base_amount']);
        $this->assertSame(160000.0, (float) $quote['total_amount']);
        $this->assertCount(4, $quote['slots']);
        $this->assertSame('15:00', $quote['start_time']);
        $this->assertSame('17:00', $quote['end_time']);
    }

    public function test_weekend_and_holiday_use_weekend_rate(): void
    {
        $court = Court::query()->where('code', 'C1')->firstOrFail();
        $service = app(PricingService::class);

        $weekend = $service->quote($court, Carbon::parse('2026-10-31'), '10:00', 60);
        $this->assertSame(110000.0, (float) $weekend['base_amount']);

        Holiday::create(['date' => '2026-10-28', 'name' => 'Libur uji', 'is_peak' => true]);
        $holiday = $service->quote($court, Carbon::parse('2026-10-28'), '10:00', 60);
        $this->assertSame(110000.0, (float) $holiday['base_amount']);
    }

    public function test_member_discount_is_applied_to_court_rental_only(): void
    {
        $court = Court::query()->where('code', 'C1')->firstOrFail();
        $type = MembershipType::query()->where('name', 'Member Bulanan')->firstOrFail();

        $member = Member::create([
            'code' => 'MBR-TEST-1',
            'name' => 'Member Uji',
            'phone' => '081200000001',
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

        $quote = app(PricingService::class)->quote($court, Carbon::parse('2026-10-26'), '10:00', 60, $member);

        $this->assertSame(60000.0, (float) $quote['base_amount']);
        $this->assertSame(6000.0, (float) $quote['discount_amount']);
        $this->assertSame(54000.0, (float) $quote['total_amount']);
    }

    public function test_duration_must_be_multiple_of_thirty_minutes(): void
    {
        $court = Court::query()->where('code', 'C1')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(PricingService::class)->quote($court, Carbon::parse('2026-10-26'), '10:00', 45);
    }
}
