<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bukti Booking {{ $booking->code }}</title>
  <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/custom.css') }}">
</head>
<body style="background:#f1f5f9">
  <div class="print-bar no-print">
    <a class="btn" href="{{ route('bookings.index', ['date' => $booking->booking_date->toDateString()]) }}">← Kembali</a>
    <div class="spacer" style="flex:1"></div>
    <button class="btn btn-primary" onclick="window.print()">Cetak bukti</button>
  </div>

  <div style="max-width:620px;margin:0 auto 30px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:28px">
    <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start">
      <div>
        <div style="font-size:20px;font-weight:800">{{ setting('venue_name', 'GOR Smash Arena') }}</div>
        <div class="muted-text">{{ setting('venue_address', '') }}</div>
        <div class="muted-text">{{ setting('venue_phone', '') }}</div>
      </div>
      <div style="text-align:right">
        <div class="badge green" style="font-size:12px">BUKTI BOOKING</div>
        <div class="mono" style="margin-top:8px;font-weight:700">{{ $booking->code }}</div>
        <div class="muted-text">{{ tanggal($booking->created_at, 'j M Y H:i') }}</div>
      </div>
    </div>

    <div class="divider" style="margin:18px 0"></div>

    <div class="grid g2" style="gap:14px">
      <div><div class="muted-text">Penyewa</div><div class="strong" style="font-size:15px">{{ $booking->customer_name }}</div><div class="muted-text">{{ $booking->customer_phone ?: '—' }}</div></div>
      <div><div class="muted-text">Member</div><div class="strong">{{ $booking->member?->code ?? 'Umum / non-member' }}</div><div class="muted-text">{{ $booking->member?->name ?? '—' }}</div></div>
      <div><div class="muted-text">Lapangan</div><div class="strong">{{ $booking->court?->name }}</div><div class="muted-text">{{ $booking->court?->type }}</div></div>
      <div><div class="muted-text">Jadwal</div><div class="strong">{{ tanggal($booking->booking_date, 'l, j F Y') }}</div><div class="muted-text">{{ jam($booking->start_time) }}–{{ jam($booking->end_time) }} ({{ $booking->duration_minutes }} menit)</div></div>
      <div><div class="muted-text">Sumber</div><div class="strong">{{ ['walkin' => 'Kunjungan langsung', 'telepon' => 'Telepon', 'wa' => 'WhatsApp'][$booking->source] ?? $booking->source }}</div></div>
      <div><div class="muted-text">Status</div><div class="strong">{{ ucfirst(str_replace('_', ' ', $booking->status)) }} · {{ ucfirst($booking->payment_status) }}</div></div>
    </div>

    <div class="divider" style="margin:18px 0"></div>
    <h4 style="margin-bottom:8px">Rincian tarif</h4>
    <table class="tbl">
      <thead><tr><th>Slot</th><th>Kategori</th><th class="num">Harga</th></tr></thead>
      <tbody>
        @foreach($booking->slots as $slot)
          <tr>
            <td>{{ jam($slot->slot_time) }}–{{ jam(\Carbon\Carbon::parse($slot->slot_time)->addMinutes(30)->format('H:i')) }}</td>
            <td>{{ $slot->category }}</td>
            <td class="num">{{ rupiah($slot->price) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="stack" style="margin-top:14px;max-width:300px;margin-left:auto">
      <div class="sum"><span class="lbl">Subtotal</span><span>{{ rupiah($booking->base_amount) }}</span></div>
      @if((float) $booking->discount_amount > 0)
        <div class="sum"><span class="lbl">Diskon member</span><span class="text-red">− {{ rupiah($booking->discount_amount) }}</span></div>
      @endif
      <div class="sum big"><span>Total</span><span>{{ rupiah($booking->total_amount) }}</span></div>
      <div class="sum"><span class="lbl">Dibayar</span><span class="text-green">{{ rupiah($booking->paid_amount) }}</span></div>
      <div class="sum"><span class="lbl">Sisa</span><span class="text-amber">{{ rupiah(max((float) $booking->total_amount - (float) $booking->paid_amount, 0)) }}</span></div>
    </div>

    @if($booking->payments->isNotEmpty())
      <div class="divider" style="margin:18px 0"></div>
      <h4 style="margin-bottom:8px">Pembayaran</h4>
      <table class="tbl">
        <thead><tr><th>Waktu</th><th>Metode</th><th>Referensi</th><th class="num">Nominal</th></tr></thead>
        <tbody>
          @foreach($booking->payments as $payment)
            <tr>
              <td>{{ tanggal($payment->paid_at, 'j M Y H:i') }}</td>
              <td>{{ strtoupper($payment->method) }}</td>
              <td>{{ $payment->reference_no ?: '—' }}</td>
              <td class="num">{{ rupiah($payment->amount) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    @if($booking->notes)
      <div class="note-box" style="margin-top:16px"><b>Catatan:</b> {{ $booking->notes }}</div>
    @endif

    <div class="alert info" style="margin-top:18px">
      <span class="ic">ℹ️</span>
      <div>Simpan bukti ini. Pembatalan mengikuti kebijakan: ≥24 jam refund 100%, 6–24 jam 50%, &lt;6 jam 0%.</div>
    </div>

    <div style="text-align:center;margin-top:22px" class="muted-text">
      {{ setting('receipt_footer', 'Terima kasih, selamat berolahraga!') }}
    </div>
  </div>
</body>
</html>
