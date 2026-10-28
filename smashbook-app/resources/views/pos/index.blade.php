@extends('layouts.app')

@section('title', 'POS Kasir')
@section('crumb', 'Penjualan cepat · stok berkurang saat transaksi dibayar')

@section('topbar-actions')
  <a class="btn btn-sm" href="{{ route('pos.history') }}">Riwayat Transaksi</a>
@endsection

@section('content')
  @php
    $paymentLabels = ['cash' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer', 'debit' => 'Debit/EDC', 'deposit' => 'Deposit'];
  @endphp

  @if(! $session)
    <div class="alert warn">
      <span class="ic">⚠️</span>
      <div><b>Belum ada sesi kas terbuka.</b> Pembayaran tunai akan ditolak server. <a href="{{ route('cash.index') }}">Buka shift kas</a> terlebih dahulu, atau gunakan pembayaran QRIS/transfer/debit.</div>
    </div>
  @endif

  <div class="pos-grid" id="posApp" data-discount-limit="{{ setting('discount_limit_pct', 20) }}">
    <div class="stack">
      <div class="card">
        <div class="card-b stack">
          <div class="pos-search-row">
            <div class="search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
              <input class="input" id="posSearch" placeholder="Scan barcode atau cari nama barang (F2)" autofocus>
            </div>
            <div class="row wrap" style="gap:6px">
              <button class="chip on" type="button" data-category="all">Semua</button>
              @foreach($categories as $category)
                <button class="chip" type="button" data-category="{{ $category }}">{{ $category }}</button>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <div class="grid g3">
        <div class="card kpi">
          <div class="row between"><span class="lbl">Transaksi shift ini</span><span class="ico violet">🧾</span></div>
          <div class="val">{{ $shiftStats['count'] }}</div>
          <div class="dlt neutral">ATV {{ rupiah($shiftStats['atv']) }}</div>
        </div>
        <div class="card kpi">
          <div class="row between"><span class="lbl">Penjualan POS shift</span><span class="ico brand">💰</span></div>
          <div class="val">{{ rupiah($shiftStats['total']) }}</div>
          <div class="dlt up">Hari ini · kasir {{ auth()->user()->name }}</div>
        </div>
        <div class="card kpi">
          <div class="row between"><span class="lbl">Item terjual</span><span class="ico blue">📦</span></div>
          <div class="val">{{ $shiftStats['items'] }}</div>
          <div class="dlt neutral">Stok otomatis berkurang saat dibayar</div>
        </div>
      </div>

      <div class="card">
        <div class="card-h">
          <div><h3>Katalog produk</h3><div class="sub">{{ $products->count() }} SKU aktif</div></div>
          <div class="right">
            @if($lowStockCount > 0)
              <span class="badge red">{{ $lowStockCount }} item stok menipis</span>
            @endif
            <a class="btn btn-sm" href="{{ route('inventory.index') }}">Stok</a>
          </div>
        </div>
        <div class="card-b">
          <div class="prod-grid">
            @foreach($products as $product)
              @php $out = $product->stock_qty <= 0 && ! $product->allow_negative; @endphp
              <button class="prod {{ $out ? 'out' : '' }}" type="button"
                data-id="{{ $product->id }}"
                data-name="{{ $product->name }}"
                data-sku="{{ $product->sku }}"
                data-price="{{ (float) $product->sell_price }}"
                data-stock="{{ $product->allow_negative ? -1 : $product->stock_qty }}"
                data-category="{{ $product->category }}">
                <div class="thumb">{{ $product->icon ?: '📦' }}</div>
                <div class="nm">{{ $product->name }}</div>
                <div class="pr">{{ rupiah($product->sell_price) }}</div>
                <div class="st">
                  <span>{{ $out ? 'Stok habis' : ($product->allow_negative ? 'Tanpa stok' : 'Stok ' . $product->stock_qty . ' ' . $product->base_unit) }}</span>
                  @if(! $out && $product->isLowStock())<span class="text-amber">⚠</span>@endif
                </div>
              </button>
            @endforeach
          </div>
        </div>
      </div>

      @if($recentSales->isNotEmpty())
        <div class="card">
          <div class="card-h"><h3>Transaksi terakhir shift ini</h3><div class="right"><a class="btn btn-sm" href="{{ route('pos.history') }}">Lihat semua</a></div></div>
          <div class="card-b tight">
            @foreach($recentSales as $sale)
              <div class="list-item">
                <div class="ic ico violet">🧾</div>
                <div class="t">
                  <b>{{ $sale->code }}</b>
                  <small>{{ $sale->created_at->format('H:i') }} · {{ $sale->items->sum('qty') }} item · {{ $sale->payments->map(fn ($p) => strtoupper($p->method))->unique()->join(', ') }}</small>
                </div>
                <div class="right">
                  <div class="strong">{{ rupiah($sale->total_amount) }}</div>
                  <a class="btn btn-sm" href="{{ route('pos.receipt', $sale) }}" target="_blank">Struk</a>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    <div class="cart">
      <form method="POST" action="{{ route('pos.checkout') }}" id="posForm" class="card">
        @csrf
        <div class="card-h">
          <div><h3>Keranjang</h3><div class="sub" id="cartJumlah">0 item</div></div>
          <div class="right"><span class="badge {{ $session ? 'green' : 'amber' }}">{{ $session ? 'Shift ' . ucfirst($session->shift) : 'Tanpa shift' }}</span></div>
        </div>
        <div class="cart-items" id="cartItems"></div>
        <div id="cartInputs"></div>

        <div class="card-b stack" style="border-top:1px solid var(--line)">
          <div class="field">
            <label>Pelanggan / member (opsional)</label>
            <select class="select" name="member_id" id="member_id">
              <option value="">— Umum —</option>
              @foreach($members as $member)
                <option value="{{ $member->id }}" data-balance="{{ (float) $member->deposit_balance }}">
                  {{ $member->name }} · {{ $member->code }} · saldo {{ rupiah($member->deposit_balance) }}
                </option>
              @endforeach
            </select>
            <span class="hint">Diskon member otomatis hanya berlaku untuk sewa lapangan. Diskon POS memakai diskon manual.</span>
          </div>

          <div class="field">
            <label>Kaitkan ke booking (opsional)</label>
            <select class="select" name="booking_id">
              <option value="">— Tidak terkait —</option>
              @foreach($bookings as $booking)
                <option value="{{ $booking->id }}">{{ $booking->code }} · {{ $booking->customer_name }} · {{ jam($booking->start_time) }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-grid" style="gap:9px">
            <div class="field">
              <label>Diskon manual (Rp)</label>
              <input class="input" type="number" min="0" step="500" name="discount_amount" id="discount_amount" value="0">
            </div>
            <div class="field">
              <label>Alasan diskon</label>
              <input class="input" name="discount_reason" maxlength="255" placeholder="Wajib bila di atas batas">
            </div>
          </div>

          <div class="field pin-row" id="discountPinRow">
            <label>PIN Owner (diskon di atas {{ setting('discount_limit_pct', 20) }}%)</label>
            <input class="input" type="password" name="owner_pin" maxlength="6" placeholder="PIN Owner">
          </div>

          <div class="divider"></div>

          <div class="pos-total-row"><span class="lbl">Subtotal</span><span id="cartSubtotal">Rp 0</span></div>
          <div class="pos-total-row"><span class="lbl">Diskon manual</span><span class="text-red" id="cartDiskon">− Rp 0</span></div>
          <div class="pos-total-row big"><span>Total</span><span id="cartTotal">Rp 0</span></div>
        </div>

        <div class="card-b stack" style="border-top:1px solid var(--line)">
          <h4>Pembayaran</h4>
          <div class="field">
            <label>Uang tunai diterima</label>
            <div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" step="1000" name="cash_received" id="cash_received" value="0"></div>
          </div>
          <div class="quick-cash">
            <button type="button" data-cash="50000">50.000</button>
            <button type="button" data-cash="100000">100.000</button>
            <button type="button" data-cash="150000">150.000</button>
            <button type="button" data-cash="200000">200.000</button>
            <button type="button" data-cash="pas">Uang pas</button>
            <button type="button" data-cash="0">Reset</button>
          </div>

          <div class="pay-input-grid">
            <div class="field"><label>QRIS</label><input class="input" type="number" min="0" step="500" name="qris" id="qris" value="0"></div>
            <div class="field"><label>Transfer</label><input class="input" type="number" min="0" step="500" name="transfer" id="transfer" value="0"></div>
            <div class="field"><label>Debit / EDC</label><input class="input" type="number" min="0" step="500" name="debit" id="debit" value="0"></div>
            <div class="field"><label>Deposit member</label><input class="input" type="number" min="0" step="500" name="deposit" id="deposit" value="0"></div>
          </div>

          <div class="pos-total-row"><span class="lbl">Tunai yang harus dibayar</span><span class="strong" id="dueCash">Rp 0</span></div>
          <div class="pos-total-row"><span class="lbl">Kembalian</span><span class="strong text-green" id="cartKembali" style="font-size:17px">Rp 0</span></div>

          <div id="paymentWarning" style="display:none"></div>

          <div class="field">
            <label>Referensi pembayaran non-tunai</label>
            <input class="input" name="reference_no" maxlength="80" placeholder="No. QRIS / transfer / EDC">
          </div>
          <div class="field">
            <label>Catatan</label>
            <input class="input" name="notes" maxlength="500" placeholder="Opsional">
          </div>

          <button class="btn btn-primary btn-lg btn-block" type="submit">Bayar &amp; Cetak Struk</button>
          <p class="muted-text">Stok berkurang saat transaksi dibayar. Void hanya pada hari yang sama dengan PIN Owner.</p>
        </div>
      </form>
    </div>
  </div>

  @if($receipt)
    <div class="modal-mask open" id="modalReceipt">
      <div class="modal" style="max-width:430px">
        <div class="modal-h">
          <div><h3>Transaksi Berhasil</h3><div class="sub">{{ $receipt->code }} · {{ $receipt->created_at->format('H:i') }}</div></div>
          <button class="x" type="button" onclick="closeModal('modalReceipt')">✕</button>
        </div>
        <div class="modal-b">
          <div class="receipt-preview">
            <div class="center">
              <div class="big">{{ setting('venue_name', 'GOR SMASH ARENA') }}</div>
              <div>{{ setting('venue_address', '') }}</div>
              <div>{{ setting('venue_phone', '') }}</div>
            </div>
            <div class="sep"></div>
            <div class="line"><span>{{ $receipt->code }}</span><span>{{ $receipt->created_at->format('H:i') }}</span></div>
            <div class="line"><span>Kasir</span><span>{{ $receipt->cashier?->name }}</span></div>
            <div class="line"><span>Pelanggan</span><span>{{ $receipt->member?->name ?? 'Umum' }}</span></div>
            <div class="sep"></div>
            @foreach($receipt->items as $item)
              <div class="line"><span>{{ $item->qty }}× {{ $item->product?->name }}</span><span>{{ rupiah($item->amount) }}</span></div>
            @endforeach
            <div class="sep"></div>
            <div class="line"><span>Subtotal</span><span>{{ rupiah($receipt->subtotal) }}</span></div>
            <div class="line"><span>Diskon</span><span>-{{ rupiah($receipt->discount_amount) }}</span></div>
            <div class="line big"><span>TOTAL</span><span>{{ rupiah($receipt->total_amount) }}</span></div>
            @foreach($receipt->payments as $payment)
              <div class="line"><span>{{ strtoupper($payment->method) }}</span><span>{{ rupiah($payment->amount) }}</span></div>
            @endforeach
            @if(session('change') !== null)
              <div class="line"><span>Kembalian</span><span>{{ rupiah(session('change')) }}</span></div>
            @endif
            <div class="sep"></div>
            <div class="center">{{ setting('receipt_footer', 'Terima kasih, selamat berolahraga!') }}</div>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalReceipt')">Tutup</button>
          <a class="btn" href="{{ route('pos.history') }}">Riwayat</a>
          <a class="btn btn-primary" href="{{ route('pos.receipt', $receipt) }}" target="_blank">Cetak Struk</a>
        </div>
      </div>
    </div>
  @endif
@endsection
