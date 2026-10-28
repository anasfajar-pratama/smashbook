<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Struk {{ $sale->code }}</title>
  <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/custom.css') }}">
</head>
<body style="background:#f1f5f9">
  <div class="print-bar no-print">
    <a class="btn" href="{{ route('pos.index') }}">← Kembali ke POS</a>
    <div class="spacer" style="flex:1"></div>
    <a class="btn" href="{{ route('pos.history') }}">Riwayat</a>
    <button class="btn btn-primary" onclick="window.print()">Cetak struk</button>
  </div>

  <div class="receipt-page">
    <div class="center">
      <div class="big">{{ setting('venue_name', 'GOR SMASH ARENA') }}</div>
      <div>{{ setting('venue_address', '') }}</div>
      <div>{{ setting('venue_phone', '') }}</div>
    </div>
    <div class="sep"></div>
    <div class="line"><span>{{ $sale->code }}</span><span>{{ $sale->created_at->format('H:i') }}</span></div>
    <div class="line"><span>Kasir</span><span>{{ $sale->cashier?->name }}</span></div>
    <div class="line"><span>Pelanggan</span><span>{{ $sale->member?->name ?? 'Umum' }}</span></div>
    @if($sale->booking)
      <div class="line"><span>Booking</span><span>{{ $sale->booking->code }}</span></div>
    @endif
    <div class="sep"></div>

    @foreach($sale->items as $item)
      <div class="line"><span>{{ $item->qty }}× {{ $item->product?->name }}</span><span>{{ rupiah($item->amount) }}</span></div>
    @endforeach

    <div class="sep"></div>
    <div class="line"><span>Subtotal</span><span>{{ rupiah($sale->subtotal) }}</span></div>
    @if((float) $sale->discount_amount > 0)
      <div class="line"><span>Diskon</span><span>-{{ rupiah($sale->discount_amount) }}</span></div>
    @endif
    @if((float) $sale->tax_amount > 0)
      <div class="line"><span>Pajak</span><span>{{ rupiah($sale->tax_amount) }}</span></div>
    @endif
    <div class="line big"><span>TOTAL</span><span>{{ rupiah($sale->total_amount) }}</span></div>
    <div class="sep"></div>

    @foreach($sale->payments as $payment)
      <div class="line"><span>{{ strtoupper($payment->method) }}</span><span>{{ rupiah($payment->amount) }}</span></div>
    @endforeach

    <div class="sep"></div>
    <div class="center">Terima kasih, selamat berolahraga!</div>
    <div class="center" style="margin-top:4px">{{ setting('receipt_footer', 'Struk ini bukti pembayaran yang sah.') }}</div>
    <div class="center" style="margin-top:8px">{{ $sale->cashier?->name }} · {{ $sale->created_at->format('d/m/Y H:i') }}</div>

    @if($sale->status === 'void')
      <div class="sep"></div>
      <div class="center" style="font-weight:800">*** VOID ***</div>
      <div class="center">{{ $sale->void_reason }}</div>
    @endif
  </div>
</body>
</html>
