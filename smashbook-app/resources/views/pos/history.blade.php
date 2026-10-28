@extends('layouts.app')

@section('title', 'Riwayat Transaksi POS')
@section('crumb', 'Penjualan, pembayaran, dan void')

@section('topbar-actions')
  <a class="btn btn-primary btn-sm" href="{{ route('pos.index') }}">+ Transaksi Baru</a>
@endsection

@section('content')
  <div class="card">
    <div class="card-b">
      <form method="GET" action="{{ route('pos.history') }}" class="filter-bar">
        <div class="field" style="min-width:150px">
          <label>Tanggal</label>
          <input class="input" type="date" name="date" value="{{ $date }}">
        </div>
        <div class="field" style="min-width:160px">
          <label>Status</label>
          <select class="select" name="status">
            <option value="">Semua status</option>
            <option value="paid" @selected($status === 'paid')>Paid</option>
            <option value="void" @selected($status === 'void')>Void</option>
          </select>
        </div>
        <div class="field" style="min-width:220px">
          <label>Cari</label>
          <input class="input" name="q" value="{{ $search }}" placeholder="Kode transaksi atau nama member">
        </div>
        <div class="field">
          <label>&nbsp;</label>
          <button class="btn">Terapkan</button>
        </div>
      </form>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Kode</th><th>Waktu</th><th>Kasir</th><th>Pelanggan</th><th class="num">Total</th><th>Pembayaran</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse($sales as $sale)
            <tr>
              <td class="mono">{{ $sale->code }}</td>
              <td class="nowrap">{{ $sale->created_at->format('H:i') }}<br><span class="muted-text">{{ tanggal($sale->created_at) }}</span></td>
              <td>{{ $sale->cashier?->name }}</td>
              <td>{{ $sale->member?->name ?? 'Umum' }}</td>
              <td class="num strong">{{ rupiah($sale->total_amount) }}</td>
              <td>
                @foreach($sale->payments->groupBy('method') as $method => $rows)
                  <span class="badge grey">{{ strtoupper($method) }} {{ rupiah($rows->sum('amount')) }}</span>
                @endforeach
              </td>
              <td><span class="badge {{ $sale->status === 'paid' ? 'green' : 'red' }}">{{ ucfirst($sale->status) }}</span></td>
              <td>
                <div class="tbl-actions">
                  <a class="btn btn-sm" href="{{ route('pos.receipt', $sale) }}" target="_blank">Struk</a>
                  @if($sale->status === 'paid')
                    <button class="btn btn-sm btn-danger" type="button" onclick="openVoid('{{ route('pos.void', $sale) }}', '{{ $sale->code }}')">Void</button>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8"><div class="empty"><span class="ic">🧾</span>Tidak ada transaksi pada filter ini.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $sales->links('partials.pagination') }}
  </div>

  <div class="modal-mask" id="modalVoid">
    <div class="modal">
      <form method="POST" id="voidForm">
        @csrf
        <div class="modal-h">
          <div><h3>Void Transaksi</h3><div class="sub"><span id="voidCode"></span> · hanya pada hari yang sama</div></div>
          <button class="x" type="button" onclick="closeModal('modalVoid')">✕</button>
        </div>
        <div class="modal-b stack">
          <div class="alert err">
            <span class="ic">⚠️</span>
            <div>Aksi ini tercatat di audit log. Stok dikembalikan dan kas tunai dikurangi. PIN Owner wajib diisi.</div>
          </div>
          <div class="field">
            <label>Alasan void <span class="req">*</span></label>
            <select class="select" name="reason" required>
              <option value="Salah input item">Salah input item</option>
              <option value="Pelanggan batal">Pelanggan batal</option>
              <option value="Barang rusak">Barang rusak</option>
              <option value="Pembayaran gagal">Pembayaran gagal</option>
              <option value="Lainnya">Lainnya</option>
            </select>
          </div>
          <div class="field">
            <label>PIN Owner <span class="req">*</span></label>
            <input class="input" type="password" name="owner_pin" required maxlength="6" placeholder="Contoh: 1234">
          </div>
          <label class="check-row"><input type="checkbox" name="restore_stock" value="1" checked> Kembalikan stok barang</label>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalVoid')">Batal</button>
          <button class="btn btn-danger">Proses void</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  function openVoid(action, code) {
    document.getElementById('voidForm').action = action;
    document.getElementById('voidCode').textContent = code;
    openModal('modalVoid');
  }
</script>
@endpush
