<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CashService;
use App\Services\PricingService;
use Database\Seeders\CourtSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, UserSeeder::class, CourtSeeder::class]);
        $this->kasir = User::query()->where('email', 'dewi@smasharena.id')->firstOrFail();
        $this->court = Court::query()->where('code', 'C1')->firstOrFail();
        $this->actingAs($this->kasir);
    }

    private function payload(string $start, int $duration = 60): array
    {
        return [
            'customer_name' => 'Penyewa Uji',
            'customer_phone' => '081200000099',
            'court_id' => $this->court->id,
            'booking_date' => now()->toDateString(),
            'start_time' => $start,
            'duration_minutes' => $duration,
            'source' => 'walkin',
        ];
    }

    public function test_booking_creates_slots_and_blocks_overlap(): void
    {
        $service = app(BookingService::class);
        $booking = $service->create($this->payload('10:00'), $this->kasir);

        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(2, CourtSlot::query()->where('booking_id', $booking->id)->count());
        $this->assertGreaterThan(0, (float) $booking->total_amount);

        $this->expectException(ValidationException::class);
        $service->create($this->payload('10:30'), $this->kasir);
    }

    public function test_expired_hold_is_released_and_slot_can_be_booked(): void
    {
        $service = app(BookingService::class);

        $hold = $service->create($this->payload('12:00'), $this->kasir, true);
        $this->assertSame('held', $hold->status);

        $hold->update(['expires_at' => now()->subMinute()]);
        CourtSlot::query()->where('booking_id', $hold->id)->update(['expires_at' => now()->subMinute()]);

        $new = $service->create($this->payload('12:00'), $this->kasir);

        $this->assertSame('confirmed', $new->status);
        $this->assertSame('expired', $hold->fresh()->status);
    }

    public function test_check_in_and_check_out_transitions(): void
    {
        $service = app(BookingService::class);
        $booking = $service->create($this->payload('14:00'), $this->kasir);

        $service->checkIn($booking);
        $this->assertSame('checked_in', $booking->fresh()->status);

        $service->checkOut($booking->fresh());
        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_payment_is_recorded_and_cash_movement_is_created(): void
    {
        $cash = app(CashService::class);
        $session = $cash->open($this->kasir, 'pagi', 300000);

        $service = app(BookingService::class);
        $booking = $service->create($this->payload('16:00'), $this->kasir);
        $quote = app(PricingService::class)->quote($this->court, now(), '16:00', 60);

        $service->addPayment($booking, 'cash', (float) $booking->total_amount, $this->kasir);

        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertSame(1, $booking->payments()->where('method', 'cash')->count());
        $this->assertGreaterThanOrEqual((float) $booking->total_amount, $session->fresh()->expectedBalance());
    }

    public function test_confirmation_with_zero_payment_requires_pay_at_venue(): void
    {
        $this->post(route('bookings.store'), $this->payload('10:00') + [
            'hold' => 0,
            'payment_amount' => 0,
        ])->assertSessionHasErrors('payment_amount');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_pay_at_venue_confirms_booking_without_payment(): void
    {
        $this->post(route('bookings.store'), $this->payload('10:00') + [
            'hold' => 0,
            'payment_amount' => 0,
            'pay_at_venue' => 1,
        ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertSame(0.0, (float) $booking->paid_amount);
    }

    public function test_operator_can_confirm_booking_without_payment(): void
    {
        $operator = User::query()->where('email', 'operator@smasharena.id')->firstOrFail();
        $this->actingAs($operator);

        $this->post(route('bookings.store'), $this->payload('10:00') + [
            'hold' => 0,
            'payment_amount' => 0,
        ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('unpaid', $booking->payment_status);
    }

    public function test_held_booking_is_confirmed_when_payment_is_recorded(): void
    {
        app(CashService::class)->open($this->kasir, 'pagi', 300000);

        $service = app(BookingService::class);
        $hold = $service->create($this->payload('18:00'), $this->kasir, true);
        $this->assertSame('held', $hold->status);

        $this->post(route('bookings.payment', $hold), [
            'method' => 'cash',
            'amount' => (float) $hold->total_amount,
        ])->assertRedirect();

        $hold->refresh();
        $this->assertSame('confirmed', $hold->status);
        $this->assertNull($hold->expires_at);
        $this->assertSame('paid', $hold->payment_status);
        $this->assertSame(0, CourtSlot::query()->where('booking_id', $hold->id)->where('status', 'held')->count());
    }

    public function test_held_booking_exposes_payment_actions_on_board_and_list(): void
    {
        $hold = app(BookingService::class)->create($this->payload('18:00'), $this->kasir, true);

        $this->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('Klik untuk mencatat pembayaran', false)
            ->assertSee('min="1" step="1" name="amount"', false)
            ->assertSee('min="0" step="1" name="payment_amount"', false);

        $this->get(route('bookings.index', ['tab' => 'list']))
            ->assertOk()
            ->assertSee(route('bookings.payment', $hold), false)
            ->assertSee(route('bookings.confirm', $hold), false);
    }

    public function test_held_booking_can_be_confirmed_without_payment(): void
    {
        $service = app(BookingService::class);
        $hold = $service->create($this->payload('18:00'), $this->kasir, true);

        $this->post(route('bookings.confirm', $hold))->assertRedirect();

        $hold->refresh();
        $this->assertSame('confirmed', $hold->status);
        $this->assertNull($hold->expires_at);
        $this->assertSame(0, CourtSlot::query()->where('booking_id', $hold->id)->where('status', 'held')->count());
    }

    public function test_expired_hold_cannot_be_paid(): void
    {
        $service = app(BookingService::class);
        $hold = $service->create($this->payload('18:00'), $this->kasir, true);
        $hold->update(['expires_at' => now()->subMinute()]);

        try {
            $service->addPayment($hold, 'cash', 10000, $this->kasir);
            $this->fail('Pembayaran hold kedaluwarsa seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('expired', $hold->fresh()->status);
            $this->assertSame(0, CourtSlot::query()->where('booking_id', $hold->id)->count());
        }
    }
}
