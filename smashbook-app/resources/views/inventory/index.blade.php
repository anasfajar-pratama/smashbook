@extends('layouts.app')

@section('title', 'Stock Barang')
@section('crumb', 'Kartu stok, HPP rata-rata bergerak, opname, dan alert minimum')

@section('topbar-actions')
  @if(auth()->user()->isAdmin())
    <button class="btn btn-sm" type="button" onclick="openModal('modalProduct')">+ Barang</button>
    <button class="btn btn-primary btn-sm" type="button" onclick="openModal('modalMasuk')">+ Stok Masuk</button>
  @endif
@endsection

@section('content')
  @php
    $tab = request('tab', 'daftar');
    $canSeeCost = auth()->user()->canSeeCost();
    $tabLinks = ['daftar' => 'Daftar Barang & Stok', 'masuk' => 'Stok Masuk', 'keluar' => 'Stok Keluar / Internal', 'opname' => 'Riwayat Opname'];
    $reasonLabels = [
      'stok_awal' => 'Stok awal', 'stok_masuk' => 'Stok masuk', 'penerimaan_po' => 'Penerimaan PO',
      'penjualan_pos' => 'Penjualan POS', 'pemakaian_internal' => 'Pemakaian internal', 'rusak' => 'Barang rusak',
      'kedaluwarsa' => 'Kedaluwarsa', 'hilang' => 'Hilang', 'salah_catat' => 'Salah catat', 'komplimen' => 'Komplimen',
      'void_penjualan' => 'Void penjualan', 'pembelian' => 'Pembelian langsung', 'retur' => 'Retur', 'koreksi' => 'Koreksi', 'hibah' => 'Hibah',
    ];
  @endphp

  <div class="grid g4">
    <div class="card kpi">
      <div class="row between">
        <span class="lbl">{{ $canSeeCost ? 'Nilai persediaan' : 'SKU aktif' }}</span>
        <span class="ico brand">📦</span>
      </div>
      <div class="val">{{ $canSeeCost ? rupiah($stats['stock_value']) : $stats['sku_count'] }}</div>
      <div class="dlt neutral">{{ $canSeeCost ? $stats['sku_count'] . ' SKU aktif · rata-rata bergerak' : 'Master barang aktif' }}</div>
    </div>
    <div class="card kpi"><div class="row between"><span class="lbl">Di bawah minimum</span><span class="ico amber">⚠️</span></div><div class="val">{{ $stats['low_count'] }}</div><div class="dlt down">Perlu segera dibeli</div></div>
    <div class="card kpi"><div class="row between"><span class="lbl">Stok habis</span><span class="ico red">🚫</span></div><div class="val">{{ $stats['out_count'] }}</div><div class="dlt neutral">Tidak bisa dijual di POS</div></div>
    <div class="card kpi"><div class="row between"><span class="lbl">Opname terakhir</span><span class="ico blue">📋</span></div><div class="val" style="font-size:18px">{{ $stats['last_opname'] ? tanggal($stats['last_opname']->finalized_at) : '—' }}</div><div class="dlt neutral">{{ $stats['last_opname'] ? ($canSeeCost ? 'selisih ' . rupiah($stats['last_opname']->total_variance_value) : 'opname selesai') : 'belum pernah' }}</div></div>
  </div>

  @if($lowStock->isNotEmpty())
    <div class="alert warn">
      <span class="ic">⚠️</span>
      <div>
        <b>{{ $lowStock->count() }} item perlu dibeli ulang</b> —
        {{ $lowStock->take(4)->map(fn ($p) => $p->name . ' (sisa ' . $p->stock_qty . ', min ' . $p->min_stock . ')')->join(' · ') }}
        @if(auth()->user()->isAdmin()) <a href="{{ route('purchase-orders.index', ['tab' => 'quick']) }}">Buat Quick PO →</a>@endif
      </div>
    </div>
  @endif

  <div class="card">
    <div class="tabs-bar">
      @foreach($tabLinks as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }}</a>
      @endforeach
      <div class="right"><a class="btn btn-sm" href="{{ route('purchase-orders.index', ['tab' => 'quick']) }}">Quick PO</a></div>
    </div>

    @if($tab === 'daftar')
      <div class="card-b">
        <form method="GET" action="{{ route('inventory.index') }}" class="filter-bar">
          <input type="hidden" name="tab" value="daftar">
          <div class="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input class="input" name="q" value="{{ $search }}" placeholder="Cari SKU atau nama barang...">
          </div>
          <div class="field" style="min-width:150px">
            <select class="select" name="category">
              <option value="">Semua kategori</option>
              @foreach($categories as $categoryOption)
                <option value="{{ $categoryOption }}" @selected($category === $categoryOption)>{{ $categoryOption }}</option>
              @endforeach
            </select>
          </div>
          <div class="field" style="min-width:140px">
            <select class="select" name="status">
              <option value="">Semua stok</option>
              <option value="low" @selected($status === 'low')>Menipis</option>
              <option value="out" @selected($status === 'out')>Habis</option>
              <option value="safe" @selected($status === 'safe')>Aman</option>
            </select>
          </div>
          <button class="btn">Terapkan</button>
          <div class="spacer"></div>
          <span class="muted-text">{{ $products->count() }} SKU ditampilkan</span>
        </form>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Barang</th><th>Kategori</th><th>Satuan</th><th class="num">Stok</th><th class="num">Min</th>@if(auth()->user()->canSeeCost())<th class="num">Harga beli</th>@endif<th class="num">Harga jual</th>@if(auth()->user()->canSeeCost())<th class="num">Margin</th><th class="num">Nilai</th>@endif<th></th></tr></thead>
          <tbody>
            @forelse($products as $product)
              <tr>
                <td>
                  <div class="member-cell">
                    <div class="icon-badge" style="width:34px;height:34px;font-size:17px">{{ $product->icon ?: '📦' }}</div>
                    <div><div class="strong">{{ $product->name }}</div><div class="muted-text mono">{{ $product->sku }}</div></div>
                  </div>
                </td>
                <td><span class="badge grey">{{ $product->category }}</span></td>
                <td>{{ $product->base_unit }}@if($product->purchase_unit && $product->conversion_qty > 1)<br><span class="muted-text">1 {{ $product->purchase_unit }} = {{ $product->conversion_qty }}</span>@endif</td>
                <td class="num strong {{ $product->stock_qty <= 0 && ! $product->allow_negative ? 'text-red' : ($product->isLowStock() ? 'text-amber' : '') }}">
                  @if($product->allow_negative)
                    <span class="muted-text">Tanpa stok</span>
                  @else
                    {{ $product->stock_qty }}
                  @endif
                </td>
                <td class="num">{{ $product->allow_negative ? '—' : $product->min_stock }}</td>
                @if(auth()->user()->canSeeCost())
                  <td class="num">{{ $product->allow_negative ? '—' : rupiah($product->cost_price) }}</td>
                @endif
                <td class="num">{{ rupiah($product->sell_price) }}</td>
                @if(auth()->user()->canSeeCost())
                  <td class="num">{{ number_format($product->marginPct(), 0, ',', '.') }}%</td>
                  <td class="num strong">{{ $product->allow_negative ? '—' : rupiah((float) $product->stock_qty * (float) $product->cost_price) }}</td>
                @endif
                <td>
                  <div class="tbl-actions">
                    <a class="btn btn-sm" href="{{ route('inventory.index', ['tab' => 'daftar', 'card' => $product->id]) }}">Kartu Stok</a>
                    @if(auth()->user()->isAdmin())
                      <button class="btn btn-sm" type="button"
                        data-product-edit="{{ $product->id }}"
                        data-name="{{ $product->name }}"
                        data-category="{{ $product->category }}"
                        data-base-unit="{{ $product->base_unit }}"
                        data-purchase-unit="{{ $product->purchase_unit }}"
                        data-conversion="{{ $product->conversion_qty }}"
                        data-cost="{{ $product->cost_price }}"
                        data-sell="{{ $product->sell_price }}"
                        data-min="{{ $product->min_stock }}"
                        data-reorder="{{ $product->reorder_point }}"
                        data-location="{{ $product->location }}"
                        data-icon="{{ $product->icon }}"
                        data-allow-negative="{{ $product->allow_negative ? 1 : 0 }}"
                        onclick="openProductEdit(this)">Ubah</button>
                      @if($product->isLowStock())
                        <a class="btn btn-sm btn-primary" href="{{ route('purchase-orders.index', ['tab' => 'quick']) }}">PO</a>
                      @endif
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="11"><div class="empty"><span class="ic">📦</span>Tidak ada barang pada filter ini.</div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="tfoot">
        @if($canSeeCost)
          <span class="lbl">Total nilai persediaan (qty × HPP rata-rata)</span>
          <span class="grand">{{ rupiah($stats['stock_value']) }}</span>
        @else
          <span class="lbl">HPP dan nilai persediaan hanya tersedia untuk Owner/Admin.</span>
        @endif
      </div>
    @endif

    @if($tab === 'masuk')
      <div class="grid g-2-1">
        <div class="card" style="box-shadow:none">
          <div class="card-h"><h3>Riwayat stok masuk</h3></div>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr><th>Tanggal</th><th>Barang</th><th class="num">Qty</th><th class="num">HPP</th><th>Sumber</th><th>Petugas</th></tr></thead>
              <tbody>
                @forelse($movements->where('type', 'in') as $movement)
                  <tr>
                    <td class="nowrap">{{ tanggal($movement->created_at, 'j M Y H:i') }}</td>
                    <td class="strong">{{ $movement->product?->name }}</td>
                    <td class="num v in">+ {{ $movement->qty }} {{ $movement->product?->base_unit }}</td>
                    <td class="num">{{ rupiah($movement->unit_cost) }}</td>
                    <td><span class="badge blue">{{ $reasonLabels[$movement->reason] ?? $movement->reason }}</span></td>
                    <td>{{ $movement->creator?->name }}</td>
                  </tr>
                @empty
                  <tr><td colspan="6"><div class="empty"><span class="ic">📥</span>Belum ada stok masuk.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="card" style="box-shadow:none">
          <div class="card-h"><h3>Catat Stok Masuk</h3></div>
          <form method="POST" action="{{ route('inventory.stock-in') }}" class="card-b stack">
            @csrf
            <div class="field"><label>Barang</label><select class="select" name="product_id" required>@foreach($allProducts as $product)<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select></div>
            <div class="field"><label>Qty masuk (satuan dasar)</label><input class="input" type="number" min="1" name="qty" required></div>
            <div class="field"><label>Harga beli per satuan dasar</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" step="100" name="unit_cost" required></div></div>
            <div class="field"><label>Sumber</label><select class="select" name="source" required><option value="pembelian">Pembelian langsung</option><option value="retur">Retur penjualan</option><option value="koreksi">Koreksi</option><option value="hibah">Hibah</option></select></div>
            <div class="field"><label>Catatan</label><input class="input" name="notes" maxlength="300"></div>
            <div class="note-box">Stok naik, HPP rata-rata dihitung ulang dalam transaksi yang sama. Tidak ada edit stok langsung.</div>
            <button class="btn btn-primary" @disabled(! auth()->user()->isAdmin())>Simpan stok masuk</button>
          </form>
        </div>
      </div>
    @endif

    @if($tab === 'keluar')
      <div class="grid g-2-1">
        <div class="card" style="box-shadow:none">
          <div class="card-h"><h3>Riwayat stok keluar</h3></div>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr><th>Tanggal</th><th>Barang</th><th class="num">Qty</th><th>Alasan</th><th>Petugas</th></tr></thead>
              <tbody>
                @forelse($movements->where('type', 'out') as $movement)
                  <tr>
                    <td class="nowrap">{{ tanggal($movement->created_at, 'j M Y H:i') }}</td>
                    <td class="strong">{{ $movement->product?->name }}</td>
                    <td class="num v out">− {{ $movement->qty }} {{ $movement->product?->base_unit }}</td>
                    <td><span class="badge {{ in_array($movement->reason, ['rusak', 'hilang', 'kedaluwarsa']) ? 'red' : 'violet' }}">{{ $reasonLabels[$movement->reason] ?? $movement->reason }}</span></td>
                    <td>{{ $movement->creator?->name }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5"><div class="empty"><span class="ic">📤</span>Belum ada stok keluar manual.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="card" style="box-shadow:none">
          <div class="card-h"><h3>Catat Stok Keluar</h3></div>
          <form method="POST" action="{{ route('inventory.stock-out') }}" class="card-b stack">
            @csrf
            <div class="field"><label>Barang</label><select class="select" name="product_id" required>@foreach($allProducts as $product)<option value="{{ $product->id }}">{{ $product->name }} · stok {{ $product->stock_qty }}</option>@endforeach</select></div>
            <div class="field"><label>Qty keluar</label><input class="input" type="number" min="1" name="qty" required></div>
            <div class="field"><label>Alasan</label><select class="select" name="reason" required><option value="pemakaian_internal">Pemakaian internal</option><option value="rusak">Barang rusak</option><option value="kedaluwarsa">Kedaluwarsa</option><option value="hilang">Hilang</option><option value="salah_catat">Salah catat</option><option value="komplimen">Komplimen</option></select></div>
            <div class="field"><label>Catatan</label><input class="input" name="notes" maxlength="300" placeholder="Contoh: dipakai panitia turnamen"></div>
            <div class="note-box">Stok minus ditolak kecuali barang ditandai <span class="mono">allow_negative</span>.</div>
            <button class="btn btn-primary" @disabled(! auth()->user()->isAdmin())>Simpan stok keluar</button>
          </form>
        </div>
      </div>
    @endif

    @if($tab === 'opname')
      <div class="card-b stack">
        <div class="alert info"><span class="ic">ℹ️</span><div>Setiap perubahan stok tercatat sebagai movement. Finalisasi opname membuat adjustment dan tidak dapat dihapus — koreksi dilakukan dengan adjustment baru.</div></div>
        @if(auth()->user()->isAdmin())
          <form method="POST" action="{{ route('inventory.opname') }}">
            @csrf
            <div class="table-wrap bordered">
              <table class="tbl">
                <thead><tr><th>Barang</th><th class="num">Stok sistem</th><th class="num" style="width:130px">Hitung fisik</th><th class="num">Selisih</th><th>Alasan selisih</th></tr></thead>
                <tbody>
                  @foreach($allProducts as $product)
                    <tr>
                      <td><div class="strong">{{ $product->name }}</div><div class="muted-text mono">{{ $product->sku }} · {{ $product->base_unit }}</div></td>
                      <td class="num">{{ $product->stock_qty }}</td>
                      <td class="num"><input class="input" type="number" name="counts[{{ $product->id }}]" min="0" placeholder="{{ $product->stock_qty }}" style="text-align:right"></td>
                      <td class="num"><span class="muted-text">otomatis</span></td>
                      <td>
                        <select class="select" name="reasons[{{ $product->id }}]">
                          <option value="">—</option>
                          <option value="Barang rusak">Barang rusak</option>
                          <option value="Hilang">Hilang</option>
                          <option value="Kedaluwarsa">Kedaluwarsa</option>
                          <option value="Salah catat">Salah catat</option>
                          <option value="Retur belum dicatat">Retur belum dicatat</option>
                        </select>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <div class="row between mt-2">
              <input class="input" name="notes" placeholder="Catatan opname (opsional)" maxlength="500" style="max-width:420px">
              <button class="btn btn-primary" data-confirm="Finalisasi opname? Adjustment stok akan dibuat permanen.">Finalisasi Opname</button>
            </div>
          </form>
        @endif

        <div class="table-wrap" style="margin-top:8px">
          <table class="tbl">
            <thead><tr><th>Kode</th><th>Tanggal</th><th class="num">Item dihitung</th><th class="num">Selisih nilai</th><th>Petugas</th><th>Disetujui</th><th>Status</th></tr></thead>
            <tbody>
              @forelse($opnames as $opname)
                <tr>
                  <td class="mono">{{ $opname->code }}</td>
                  <td>{{ tanggal($opname->opname_date) }}</td>
                  <td class="num">{{ $opname->items()->count() }}</td>
                  <td class="num {{ (float) $opname->total_variance_value < 0 ? 'text-red' : 'text-green' }}">{{ rupiah($opname->total_variance_value) }}</td>
                  <td>{{ $opname->creator?->name }}</td>
                  <td>{{ $opname->finalizer?->name ?? '—' }}</td>
                  <td><span class="badge {{ $opname->status === 'finalized' ? 'green' : 'amber' }}">{{ ucfirst($opname->status) }}</span></td>
                </tr>
              @empty
                <tr><td colspan="7"><div class="empty"><span class="ic">📋</span>Belum ada opname.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>

  @if($cardProduct)
    <div class="modal-mask open" id="modalKartu">
      <div class="modal wide">
        <div class="modal-h">
          <div><h3>Kartu Stok — {{ $cardProduct->name }}</h3><div class="sub">{{ $cardProduct->sku }} · {{ $cardProduct->base_unit }} · min {{ $cardProduct->min_stock }} · lokasi {{ $cardProduct->location ?: '—' }}</div></div>
          <button class="x" type="button" onclick="closeModal('modalKartu')">✕</button>
        </div>
        <div class="modal-b">
          <div class="grid g4">
            <div class="card kpi" style="box-shadow:none"><span class="lbl">Stok saat ini</span><div class="val" style="font-size:19px">{{ $cardProduct->allow_negative ? 'Tanpa stok' : $cardProduct->stock_qty . ' ' . $cardProduct->base_unit }}</div></div>
            <div class="card kpi" style="box-shadow:none"><span class="lbl">Kategori</span><div class="val" style="font-size:19px">{{ $cardProduct->category }}</div></div>
            @if($canSeeCost)
              <div class="card kpi" style="box-shadow:none"><span class="lbl">HPP rata-rata</span><div class="val" style="font-size:19px">{{ rupiah($cardProduct->cost_price) }}</div></div>
              <div class="card kpi" style="box-shadow:none"><span class="lbl">Nilai persediaan</span><div class="val" style="font-size:19px">{{ $cardProduct->allow_negative ? '—' : rupiah((float) $cardProduct->stock_qty * (float) $cardProduct->cost_price) }}</div></div>
              <div class="card kpi" style="box-shadow:none"><span class="lbl">Margin jual</span><div class="val" style="font-size:19px">{{ number_format($cardProduct->marginPct(), 0, ',', '.') }}%</div></div>
            @else
              <div class="card kpi" style="box-shadow:none"><span class="lbl">Lokasi</span><div class="val" style="font-size:19px">{{ $cardProduct->location ?: '—' }}</div></div>
              <div class="card kpi" style="box-shadow:none"><span class="lbl">Minimum stok</span><div class="val" style="font-size:19px">{{ $cardProduct->min_stock }}</div></div>
            @endif
          </div>
          <div class="table-wrap" style="margin-top:12px">
            <table class="tbl">
              <thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th>@if($canSeeCost)<th class="num">HPP</th>@endif<th class="num">Saldo</th><th>Petugas</th></tr></thead>
              <tbody>
                @forelse($cardProduct->movements as $movement)
                  <tr>
                    <td class="nowrap">{{ tanggal($movement->created_at, 'j M Y H:i') }}</td>
                    <td><span class="badge {{ $movement->type === 'in' ? 'green' : ($movement->type === 'out' ? 'violet' : 'red') }}">{{ strtoupper($movement->type) }}</span></td>
                    <td>{{ $movement->notes ?: ($reasonLabels[$movement->reason] ?? $movement->reason) }}</td>
                    <td class="num v in">{{ $movement->type === 'in' ? '+' . $movement->qty : '' }}</td>
                    <td class="num v out">{{ $movement->type === 'out' ? '−' . $movement->qty : ($movement->type === 'adjust' ? $movement->qty : '') }}</td>
                    @if($canSeeCost)<td class="num">{{ rupiah($movement->unit_cost) }}</td>@endif
                    <td class="num strong">{{ $movement->balance_after }}</td>
                    <td>{{ $movement->creator?->name }}</td>
                  </tr>
                @empty
                  <tr><td colspan="{{ $canSeeCost ? 8 : 7 }}"><div class="empty">Belum ada movement.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalKartu')">Tutup</button>
          <a class="btn" href="{{ route('inventory.index', ['tab' => 'daftar']) }}">Tutup &amp; kembali</a>
        </div>
      </div>
    </div>
  @endif

  @if(auth()->user()->isAdmin())
    <div class="modal-mask" id="modalProduct">
      <div class="modal wide">
        <form method="POST" action="{{ route('inventory.products.store') }}">
          @csrf
          <div class="modal-h"><div><h3>Barang Baru</h3><div class="sub">Stok awal dicatat sebagai movement IN.</div></div><button class="x" type="button" onclick="closeModal('modalProduct')">✕</button></div>
          <div class="modal-b">
            <div class="form-grid">
              <div class="field"><label>SKU</label><input class="input" name="sku" required maxlength="30" placeholder="MNM-007"></div>
              <div class="field"><label>Nama</label><input class="input" name="name" required maxlength="120"></div>
              <div class="field"><label>Kategori</label><input class="input" name="category" required maxlength="40" placeholder="Minuman"></div>
              <div class="field"><label>Satuan dasar</label><input class="input" name="base_unit" required maxlength="20" placeholder="botol"></div>
              <div class="field"><label>Satuan beli</label><input class="input" name="purchase_unit" maxlength="20" placeholder="dus"></div>
              <div class="field"><label>Konversi</label><input class="input" type="number" name="conversion_qty" min="1" value="1"></div>
              <div class="field"><label>Harga beli (basis)</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" name="cost_price" required></div></div>
              <div class="field"><label>Harga jual</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" name="sell_price" required></div></div>
              <div class="field"><label>Stok awal</label><input class="input" type="number" min="0" name="stock_qty" value="0"></div>
              <div class="field"><label>Minimum stok</label><input class="input" type="number" min="0" name="min_stock" value="0"></div>
              <div class="field"><label>Reorder point</label><input class="input" type="number" min="0" name="reorder_point" value="0"></div>
              <div class="field"><label>Lokasi</label><input class="input" name="location" maxlength="60" placeholder="Kulkas / Gudang"></div>
              <div class="field"><label>Ikon</label><input class="input" name="icon" maxlength="10" placeholder="💧"></div>
              <div class="field"><label>&nbsp;</label><label class="check-row"><input type="checkbox" name="allow_negative" value="1"> Izinkan stok minus (jasa)</label></div>
            </div>
          </div>
          <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalProduct')">Batal</button><button class="btn btn-primary">Simpan barang</button></div>
        </form>
      </div>
    </div>

    <div class="modal-mask" id="modalProductEdit">
      <div class="modal wide">
        <form method="POST" id="productEditForm">
          @csrf @method('PUT')
          <div class="modal-h"><div><h3>Ubah Master Barang</h3><div class="sub">Stok tidak dapat diubah di sini — gunakan stok masuk/keluar/opname.</div></div><button class="x" type="button" onclick="closeModal('modalProductEdit')">✕</button></div>
          <div class="modal-b">
            <div class="form-grid">
              <div class="field full"><label>Nama</label><input class="input" name="name" id="pe_name" required maxlength="120"></div>
              <div class="field"><label>Kategori</label><input class="input" name="category" id="pe_category" required maxlength="40"></div>
              <div class="field"><label>Satuan dasar</label><input class="input" name="base_unit" id="pe_base_unit" required maxlength="20"></div>
              <div class="field"><label>Satuan beli</label><input class="input" name="purchase_unit" id="pe_purchase_unit" maxlength="20"></div>
              <div class="field"><label>Konversi</label><input class="input" type="number" name="conversion_qty" id="pe_conversion" min="1"></div>
              <div class="field"><label>Harga beli</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" name="cost_price" id="pe_cost" required></div></div>
              <div class="field"><label>Harga jual</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" name="sell_price" id="pe_sell" required></div></div>
              <div class="field"><label>Minimum stok</label><input class="input" type="number" min="0" name="min_stock" id="pe_min"></div>
              <div class="field"><label>Reorder point</label><input class="input" type="number" min="0" name="reorder_point" id="pe_reorder"></div>
              <div class="field"><label>Lokasi</label><input class="input" name="location" id="pe_location" maxlength="60"></div>
              <div class="field"><label>Ikon</label><input class="input" name="icon" id="pe_icon" maxlength="10"></div>
              <div class="field"><label>&nbsp;</label><label class="check-row"><input type="checkbox" name="allow_negative" id="pe_allow_negative" value="1"> Izinkan stok minus</label></div>
            </div>
          </div>
          <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalProductEdit')">Batal</button><button class="btn btn-primary">Simpan perubahan</button></div>
        </form>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
<script>
  function openProductEdit(button) {
    const form = document.getElementById('productEditForm');
    form.action = '{{ url('inventory/products') }}/' + button.dataset.productEdit;
    const map = {
      pe_name: 'name', pe_category: 'category', pe_base_unit: 'baseUnit', pe_purchase_unit: 'purchaseUnit',
      pe_conversion: 'conversion', pe_cost: 'cost', pe_sell: 'sell', pe_min: 'min', pe_reorder: 'reorder',
      pe_location: 'location', pe_icon: 'icon'
    };
    Object.entries(map).forEach(([id, key]) => {
      const el = document.getElementById(id);
      if (el) el.value = button.dataset[key] || '';
    });
    document.getElementById('pe_allow_negative').checked = button.dataset.allowNegative === '1';
    openModal('modalProductEdit');
  }
</script>
@endpush
