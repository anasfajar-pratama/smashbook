@extends('layouts.app')

@section('title', 'Quick PO & Biaya Operasional')
@section('crumb', 'Pengadaan cepat dengan jejak audit')

@section('topbar-actions')
  <button class="btn btn-primary btn-sm" type="button" onclick="document.getElementById('poForm')?.scrollIntoView({behavior:'smooth'})">+ PO Baru</button>
@endsection

@section('content')
  @php
    $tab = request('tab', 'quick');
    $tabLinks = ['quick' => '⚡ Quick PO', 'list' => 'Daftar Purchase Order', 'opex' => 'Biaya Operasional', 'supplier' => 'Supplier'];
    $statusMeta = [
      'draft' => ['Draf', 'grey'],
      'submitted' => ['Menunggu approval', 'amber'],
      'approved' => ['Dalam pengiriman', 'blue'],
      'received' => ['Diterima', 'green'],
      'rejected' => ['Ditolak', 'red'],
      'cancelled' => ['Dibatalkan', 'grey'],
      'closed' => ['Selesai', 'green'],
    ];
    $expenseCategories = ['listrik' => 'Listrik', 'air' => 'Air', 'sewa' => 'Sewa', 'gaji' => 'Gaji', 'kebersihan' => 'Kebersihan', 'perawatan' => 'Perawatan', 'internet' => 'Internet', 'lain_lain' => 'Lain-lain'];
  @endphp

  <div class="grid g4">
    <div class="card kpi"><div class="row between"><span class="lbl">Menunggu approval</span><span class="ico amber">📝</span></div><div class="val">{{ $stats['pending_count'] }}</div><div class="dlt neutral">Total {{ rupiah($stats['pending_total']) }}</div></div>
    <div class="card kpi"><div class="row between"><span class="lbl">Dalam pengiriman</span><span class="ico blue">🚚</span></div><div class="val">{{ $stats['incoming_count'] }}</div><div class="dlt neutral">PO disetujui, menunggu barang</div></div>
    <div class="card kpi"><div class="row between"><span class="lbl">Nilai PO bulan ini</span><span class="ico violet">📦</span></div><div class="val">{{ rupiah($stats['month_purchase']) }}</div><div class="dlt neutral">{{ now()->locale('id')->translatedFormat('F Y') }}</div></div>
    <div class="card kpi"><div class="row between"><span class="lbl">Biaya operasional bulan ini</span><span class="ico red">🧾</span></div><div class="val">{{ rupiah($stats['month_expense']) }}</div><div class="dlt neutral">Seluruh kategori opex</div></div>
  </div>

  <div class="card">
    <div class="tabs-bar">
      @foreach($tabLinks as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }}</a>
      @endforeach
    </div>

    @if($tab === 'quick')
      <form method="POST" action="{{ route('purchase-orders.store') }}" enctype="multipart/form-data" id="poForm"
        data-admin-threshold="{{ setting('po_admin_threshold', 500000) }}"
        data-owner-threshold="{{ setting('po_owner_threshold', 2000000) }}">
        @csrf
        <div class="card-b grid g-3-2">
          <div class="stack">
            <div class="alert info">
              <span class="ic">⚡</span>
              <div><b>Quick PO dalam 3 langkah:</b> pilih supplier → tambahkan item → simpan &amp; ajukan. Item produk menambah stok saat diterima; item biaya langsung menjadi expense.</div>
            </div>

            <div class="card" style="box-shadow:none">
              <div class="card-h"><h3>Data PO</h3><div class="right"><span class="badge" id="poApproval">Auto-approve Admin</span></div></div>
              <div class="card-b">
                <div class="form-grid">
                  <div class="field">
                    <label>Supplier / asal belanja</label>
                    <select class="select" name="supplier_id">
                      <option value="">Pembelian tunai (tanpa supplier)</option>
                      @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="field">
                    <label>Nama supplier (opsional)</label>
                    <input class="input" name="supplier_name" maxlength="120" placeholder="Warung Bu Tini">
                  </div>
                  <div class="field"><label>Tanggal PO</label><input class="input" type="date" name="request_date" value="{{ now()->toDateString() }}" required></div>
                  <div class="field"><label>Dibutuhkan tanggal</label><input class="input" type="date" name="expected_date" value="{{ now()->toDateString() }}"></div>
                  <div class="field"><label>Cara bayar</label><select class="select" name="payment_method" required><option value="cash">Tunai (kas shift)</option><option value="transfer">Transfer</option><option value="tempo">Tempo</option></select></div>
                  <div class="field"><label>Prioritas</label><select class="select" name="priority" required><option value="normal">Normal</option><option value="urgent">Darurat (stok habis)</option></select></div>
                </div>
              </div>
            </div>

            <div class="card" style="box-shadow:none">
              <div class="card-h">
                <h3>Item PO</h3>
                <div class="right"><button class="btn btn-sm" type="button" data-add-row>+ Baris kosong</button></div>
              </div>
              <div class="table-wrap">
                <table class="tbl">
                  <thead><tr><th>Jenis</th><th>Barang / deskripsi</th><th class="num">Qty</th><th class="num">Harga satuan</th><th class="num">Jumlah</th><th></th></tr></thead>
                  <tbody id="poItems">
                    <tr class="po-row">
                      <td>
                        <select class="select po-type" name="items[0][item_type]" style="min-width:110px">
                          <option value="product">Produk</option>
                          <option value="expense">Biaya/Jasa</option>
                        </select>
                      </td>
                      <td>
                        <div class="po-product-wrap">
                          <select class="select po-product" name="items[0][product_id]">
                            <option value="">— pilih produk —</option>
                            @foreach($products as $product)
                              <option value="{{ $product->id }}"
                                data-name="{{ $product->name }}"
                                data-cost="{{ $product->cost_price }}"
                                data-unit="{{ $product->base_unit }}"
                                data-purchase-unit="{{ $product->purchase_unit }}"
                                data-conversion="{{ $product->conversion_qty }}">
                                {{ $product->name }} · {{ $product->sku }}
                              </option>
                            @endforeach
                          </select>
                          <input class="input po-description" name="items[0][description]" placeholder="Deskripsi item" style="margin-top:6px" maxlength="160">
                        </div>
                        <div class="po-expense-wrap" style="display:none">
                          <input class="input po-description" name="items[0][description]" placeholder="Deskripsi biaya/jasa" style="margin-bottom:6px" maxlength="160">
                          <select class="select" name="items[0][expense_category]">
                            @foreach($expenseCategories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                          </select>
                        </div>
                      </td>
                      <td><input class="input po-qty" type="number" name="items[0][qty]" min="0.01" step="0.01" value="1" style="width:85px;text-align:right"></td>
                      <td><input class="input po-price" type="number" name="items[0][unit_price]" min="0" step="100" value="0" style="width:120px;text-align:right"></td>
                      <td class="num"><input type="hidden" name="items[0][unit]" class="po-unit"><span class="po-amount strong">Rp 0</span></td>
                      <td><button class="btn btn-sm btn-ghost" type="button" data-remove-row>✕</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="form-grid">
              <div class="field full"><label>Catatan / alasan pengadaan</label><textarea class="input" name="notes" rows="2" maxlength="1000" placeholder="Contoh: air mineral habis karena turnamen akhir pekan."></textarea></div>
              <div class="field full"><label>Foto nota (wajib untuk pembelian tunai &lt; Rp 500.000)</label><input class="input" type="file" name="attachment" accept="image/*,.pdf"></div>
            </div>
          </div>

          <div class="stack">
            <div class="card" style="box-shadow:none">
              <div class="card-h"><h3>Ringkasan PO</h3></div>
              <div class="card-b stack">
                <div class="form-grid" style="gap:9px">
                  <div class="field"><label>Diskon supplier</label><input class="input" type="number" name="discount_amount" id="poDiscount" min="0" step="100" value="0"></div>
                  <div class="field"><label>Pajak</label><input class="input" type="number" name="tax_amount" id="poTax" min="0" step="100" value="0"></div>
                  <div class="field full"><label>Biaya kirim</label><input class="input" type="number" name="shipping_amount" id="poShipping" min="0" step="100" value="0"></div>
                </div>
                <div class="divider"></div>
                <div class="sum"><span class="lbl">Subtotal</span><span id="poSubtotal">Rp 0</span></div>
                <div class="sum big"><span>Total</span><span id="poTotal">Rp 0</span></div>
              </div>
            </div>

            <div class="card" style="box-shadow:none">
              <div class="card-h"><h3>Kontrol &amp; kepatuhan</h3></div>
              <div class="card-b tight">
                <div class="list-item"><div class="ic ico amber">📎</div><div class="t"><b>Foto nota wajib</b><small>Tunai &lt; Rp 500.000</small></div><span class="badge amber">Wajib saat terima</span></div>
                <div class="list-item"><div class="ic ico blue">🏪</div><div class="t"><b>Perbandingan harga</b><small>HPP rata-rata diperbarui saat penerimaan</small></div><span class="badge grey">Otomatis</span></div>
                <div class="list-item"><div class="ic ico brand">✅</div><div class="t"><b>Approval berjenjang</b><small>≤ {{ rupiah(setting('po_admin_threshold', 500000)) }} auto-approve Admin; di atas {{ rupiah(setting('po_owner_threshold', 2000000)) }} wajib Owner.</small></div></div>
              </div>
            </div>

            <div class="note-box">
              PO yang sudah disetujui tidak bisa diubah nominalnya. Barang datang → penerimaan parsial diperbolehkan; item produk menaikkan stok dan HPP, item biaya membuat expense.
            </div>

            <button class="btn btn-primary btn-lg btn-block" type="submit">Simpan &amp; Ajukan PO</button>
            <button class="btn btn-block" type="submit" name="save_draft" value="1">Simpan Draf</button>
          </div>
        </div>
      </form>

      <template id="poItemTemplate">
        <tr class="po-row">
          <td>
            <select class="select po-type" name="items[__INDEX__][item_type]" style="min-width:110px">
              <option value="product">Produk</option>
              <option value="expense">Biaya/Jasa</option>
            </select>
          </td>
          <td>
            <div class="po-product-wrap">
              <select class="select po-product" name="items[__INDEX__][product_id]">
                <option value="">— pilih produk —</option>
                @foreach($products as $product)
                  <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-cost="{{ $product->cost_price }}" data-unit="{{ $product->base_unit }}" data-purchase-unit="{{ $product->purchase_unit }}" data-conversion="{{ $product->conversion_qty }}">{{ $product->name }} · {{ $product->sku }}</option>
                @endforeach
              </select>
              <input class="input po-description" name="items[__INDEX__][description]" placeholder="Deskripsi item" style="margin-top:6px" maxlength="160">
            </div>
            <div class="po-expense-wrap" style="display:none">
              <input class="input po-description" name="items[__INDEX__][description]" placeholder="Deskripsi biaya/jasa" style="margin-bottom:6px" maxlength="160">
              <select class="select" name="items[__INDEX__][expense_category]">
                @foreach($expenseCategories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
              </select>
            </div>
          </td>
          <td><input class="input po-qty" type="number" name="items[__INDEX__][qty]" min="0.01" step="0.01" value="1" style="width:85px;text-align:right"></td>
          <td><input class="input po-price" type="number" name="items[__INDEX__][unit_price]" min="0" step="100" value="0" style="width:120px;text-align:right"></td>
          <td class="num"><input type="hidden" name="items[__INDEX__][unit]" class="po-unit"><span class="po-amount strong">Rp 0</span></td>
          <td><button class="btn btn-sm btn-ghost" type="button" data-remove-row>✕</button></td>
        </tr>
      </template>
    @endif

    @if($tab === 'list')
      <div class="card-b">
        <form method="GET" action="{{ route('purchase-orders.index') }}" class="filter-bar">
          <input type="hidden" name="tab" value="list">
          <div class="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input class="input" name="q" value="{{ $search }}" placeholder="Cari nomor PO atau supplier...">
          </div>
          <div class="field" style="min-width:170px">
            <select class="select" name="status">
              <option value="">Semua status</option>
              @foreach($statusMeta as $key => $meta)<option value="{{ $key }}" @selected($filterStatus === $key)>{{ $meta[0] }}</option>@endforeach
            </select>
          </div>
          <button class="btn">Terapkan</button>
          <div class="spacer"></div>
          <span class="muted-text">{{ $orders->total() }} dokumen</span>
        </form>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Kode PO</th><th>Supplier</th><th>Jenis</th><th>Tanggal</th><th>Dibutuhkan</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @forelse($orders as $po)
              @php $meta = $statusMeta[$po->status] ?? [ucfirst($po->status), 'grey']; @endphp
              <tr>
                <td class="mono">{{ $po->code }}</td>
                <td><div class="strong">{{ $po->supplier_name ?: ($po->supplier?->name ?? 'Pembelian tunai') }}</div><div class="muted-text">{{ strtoupper($po->payment_method) }} · {{ ucfirst($po->priority) }}</div></td>
                <td><span class="badge {{ $po->po_type === 'product' ? 'blue' : ($po->po_type === 'expense' ? 'amber' : 'violet') }}">{{ ['product' => 'Produk', 'expense' => 'Biaya/Jasa', 'mixed' => 'Campuran'][$po->po_type] ?? $po->po_type }}</span></td>
                <td>{{ tanggal($po->request_date) }}</td>
                <td>{{ $po->expected_date ? tanggal($po->expected_date) : '—' }}</td>
                <td class="num strong">{{ rupiah($po->total_amount) }}</td>
                <td><span class="badge {{ $meta[1] }}">{{ $meta[0] }}</span></td>
                <td>
                  <div class="tbl-actions">
                    @if($po->status === 'submitted')
                      <a class="btn btn-sm" href="{{ route('purchase-orders.index', ['tab' => 'list', 'approve' => $po->id]) }}">Tinjau</a>
                    @elseif($po->status === 'approved')
                      <a class="btn btn-sm btn-primary" href="{{ route('purchase-orders.index', ['tab' => 'list', 'receive' => $po->id]) }}">Terima</a>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="8"><div class="empty"><span class="ic">📝</span>Belum ada PO.</div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{ $orders->links('partials.pagination') }}
    @endif

    @if($tab === 'opex')
      <div class="card-b grid g-2-1">
        <div class="card" style="box-shadow:none">
          <div class="card-h">
            <div><h3>Biaya operasional {{ now()->locale('id')->translatedFormat('F Y') }}</h3><div class="sub">{{ $expenses->count() }} transaksi</div></div>
            <div class="right"><button class="btn btn-sm btn-primary" type="button" onclick="openModal('modalExpense')">+ Catat Biaya</button></div>
          </div>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="num">Nominal</th><th>Bayar</th><th>Nota</th><th>Petugas</th></tr></thead>
              <tbody>
                @forelse($expenses as $expense)
                  <tr>
                    <td>{{ tanggal($expense->expense_date) }}</td>
                    <td><span class="badge {{ in_array($expense->category, ['listrik', 'gaji']) ? 'red' : 'grey' }}">{{ $expenseCategories[$expense->category] ?? ucfirst($expense->category) }}</span></td>
                    <td>{{ $expense->description }}</td>
                    <td class="num strong">{{ rupiah($expense->amount) }}</td>
                    <td><span class="badge {{ $expense->payment_method === 'cash' ? 'amber' : 'blue' }}">{{ strtoupper($expense->payment_method) }}</span></td>
                    <td>@if($expense->attachment)<span class="badge green">Ada</span>@else<span class="badge red">Belum</span>@endif</td>
                    <td>{{ $expense->creator?->name }}</td>
                  </tr>
                @empty
                  <tr><td colspan="7"><div class="empty"><span class="ic">🧾</span>Belum ada biaya operasional.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="stack">
          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Komposisi biaya</h3><div class="right"><span class="badge dark">{{ rupiah($expenses->sum('amount')) }}</span></div></div>
            <div class="card-b stack">
              @php $expenseTotal = max((float) $expenses->sum('amount'), 1); @endphp
              @forelse($expenseByCategory as $category => $amount)
                @php $pct = round($amount / $expenseTotal * 100); @endphp
                <div>
                  <div class="row between"><span class="muted-text">{{ $expenseCategories[$category] ?? ucfirst($category) }}</span><span class="strong">{{ rupiah($amount) }} · {{ $pct }}%</span></div>
                  <div class="bar" style="margin-top:4px"><span class="{{ $loop->index === 0 ? 'red' : ($loop->index === 1 ? 'blue' : 'amber') }}" style="width:{{ $pct }}%"></span></div>
                </div>
              @empty
                <div class="empty">Belum ada data.</div>
              @endforelse
            </div>
          </div>

          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Biaya rutin otomatis</h3><div class="right"><button class="btn btn-sm" type="button" onclick="openModal('modalRecurring')">+ Template</button></div></div>
            <div class="card-b tight">
              @forelse($recurring as $item)
                <div class="list-item">
                  <div class="ic ico {{ $item->is_active ? 'brand' : 'grey' }}">🔁</div>
                  <div class="t"><b>{{ $item->name }}</b><small>{{ rupiah($item->amount) }} · tiap tanggal {{ $item->day_of_month }}</small></div>
                  <span class="badge {{ $item->is_active ? 'green' : 'grey' }}">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
              @empty
                <div class="empty"><span class="ic">🔁</span>Belum ada biaya rutin.</div>
              @endforelse
            </div>
          </div>
        </div>
      </div>
    @endif

    @if($tab === 'supplier')
      <div class="card-b stack">
        <div class="row between">
          <div><h3>{{ $suppliers->count() }} supplier</h3><div class="muted-text">Termasuk pembelian tunai non-supplier.</div></div>
          <button class="btn btn-primary" type="button" onclick="openModal('modalSupplier')">+ Supplier Baru</button>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Supplier</th><th>Kontak</th><th>Kategori</th><th>Termin</th><th class="num">PO</th><th class="num">Nilai</th><th></th></tr></thead>
            <tbody>
              @forelse($supplierStats as $supplier)
                <tr>
                  <td><div class="strong">{{ $supplier->name }}</div><div class="muted-text">{{ $supplier->address ?: '—' }}</div></td>
                  <td>{{ $supplier->contact_person ?: '—' }}<br><span class="muted-text">{{ $supplier->phone ?: '—' }}</span></td>
                  <td>{{ $supplier->category ?: '—' }}</td>
                  <td>{{ $supplier->payment_term ?: '—' }}</td>
                  <td class="num">{{ $supplier->orders_count }}×</td>
                  <td class="num strong">{{ rupiah($supplier->orders_total) }}</td>
                  <td class="right"><a class="btn btn-sm" href="{{ route('purchase-orders.index', ['tab' => 'quick']) }}">Buat PO</a></td>
                </tr>
              @empty
                <tr><td colspan="7"><div class="empty"><span class="ic">🏪</span>Belum ada supplier.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>

  @if($approvePo)
    <div class="modal-mask open" id="modalApproval">
      <div class="modal wide">
        <div class="modal-h">
          <div><h3>Tinjau Purchase Order</h3><div class="sub">{{ $approvePo->code }} · diajukan {{ $approvePo->requester?->name }} pada {{ tanggal($approvePo->created_at, 'j M Y H:i') }}</div></div>
          <button class="x" type="button" onclick="closeModal('modalApproval')">✕</button>
        </div>
        <form method="POST" action="{{ route('purchase-orders.approve', $approvePo) }}">
          @csrf
          <div class="modal-b stack">
            @if((float) $approvePo->total_amount > (float) setting('po_admin_threshold', 500000))
              <div class="alert warn"><span class="ic">⚠️</span><div><b>Nominal melebihi threshold Admin ({{ rupiah(setting('po_admin_threshold', 500000)) }})</b> — PO ini memerlukan persetujuan Owner.</div></div>
            @endif
            <div class="grid g2" style="gap:12px">
              <div><div class="muted-text">Supplier</div><div class="strong">{{ $approvePo->supplier_name ?: ($approvePo->supplier?->name ?? 'Pembelian tunai') }}</div></div>
              <div><div class="muted-text">Jenis</div><div class="strong">{{ ['product' => 'Produk', 'expense' => 'Biaya / Jasa', 'mixed' => 'Campuran'][$approvePo->po_type] }}</div></div>
              <div><div class="muted-text">Dibutuhkan</div><div class="strong">{{ $approvePo->expected_date ? tanggal($approvePo->expected_date) : '—' }}</div></div>
              <div><div class="muted-text">Pembayaran</div><div class="strong">{{ strtoupper($approvePo->payment_method) }} · {{ ucfirst($approvePo->priority) }}</div></div>
            </div>
            <div class="table-wrap">
              <table class="tbl">
                <thead><tr><th>Deskripsi</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Jumlah</th></tr></thead>
                <tbody>
                  @foreach($approvePo->items as $item)
                    <tr><td>{{ $item->description }}</td><td class="num">{{ $item->qty }} {{ $item->unit }}</td><td class="num">{{ rupiah($item->unit_price) }}</td><td class="num strong">{{ rupiah($item->amount) }}</td></tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <div class="sum big"><span>Total</span><span>{{ rupiah($approvePo->total_amount) }}</span></div>
            <div class="field"><label>Catatan approval / alasan penolakan</label><textarea class="input" name="note" rows="2" maxlength="500"></textarea></div>
          </div>
          <div class="modal-f">
            <button class="btn btn-danger" type="submit" formaction="{{ route('purchase-orders.reject', $approvePo) }}" onclick="const t=this.form.querySelector('[name=note]'); if(!t.value.trim()){alert('Isi alasan penolakan terlebih dahulu.'); return false;}">Tolak</button>
            <button class="btn btn-primary">Setujui PO</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  @if($receivePo)
    <div class="modal-mask open" id="modalReceive">
      <div class="modal wide">
        <div class="modal-h">
          <div><h3>Penerimaan Barang — {{ $receivePo->code }}</h3><div class="sub">Bisa parsial. Qty yang diisi adalah qty yang diterima sekarang.</div></div>
          <button class="x" type="button" onclick="closeModal('modalReceive')">✕</button>
        </div>
        <form method="POST" action="{{ route('purchase-orders.receive', $receivePo) }}" enctype="multipart/form-data">
          @csrf
          <div class="modal-b stack">
            @if($receivePo->payment_method === 'cash' && (float) $receivePo->total_amount < 500000)
              <div class="alert warn"><span class="ic">⚠️</span><div>Pembelian tunai di bawah Rp 500.000 wajib melampirkan foto nota.</div></div>
            @endif
            <div class="table-wrap">
              <table class="tbl">
                <thead><tr><th>Item</th><th class="num">Dipesan</th><th class="num">Sudah diterima</th><th class="num" style="width:130px">Terima sekarang</th></tr></thead>
                <tbody>
                  @foreach($receivePo->items as $item)
                    <tr>
                      <td><div class="strong">{{ $item->description }}</div><div class="muted-text">{{ $item->item_type === 'product' ? 'Produk → stok' : 'Biaya → expense' }} · {{ $item->unit }}</div></td>
                      <td class="num">{{ $item->qty }}</td>
                      <td class="num">{{ $item->received_qty }}</td>
                      <td class="num"><input class="input" type="number" min="0" step="0.01" max="{{ $item->remainingQty() }}" name="received[{{ $item->id }}]" value="{{ $item->remainingQty() }}" style="text-align:right"></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <div class="field"><label>Foto nota</label><input class="input" type="file" name="attachment" accept="image/*,.pdf"><span class="hint">Wajib untuk pembelian tunai &lt; Rp 500.000.</span></div>
          </div>
          <div class="modal-f">
            <button class="btn" type="button" onclick="closeModal('modalReceive')">Batal</button>
            <button class="btn btn-primary">Simpan penerimaan</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  <div class="modal-mask" id="modalExpense">
    <div class="modal">
      <form method="POST" action="{{ route('purchase-orders.expenses.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-h"><div><h3>Catat Biaya Operasional</h3><div class="sub">Pengeluaran tunai mengurangi kas shift aktif.</div></div><button class="x" type="button" onclick="closeModal('modalExpense')">✕</button></div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Kategori</label><select class="select" name="category" required>@foreach($expenseCategories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label>Tanggal</label><input class="input" type="date" name="expense_date" value="{{ now()->toDateString() }}" required></div>
            <div class="field"><label>Nominal</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="1" step="100" name="amount" required></div></div>
            <div class="field"><label>Metode bayar</label><select class="select" name="payment_method" required><option value="cash">Tunai (kas shift)</option><option value="transfer">Transfer</option><option value="debit">Debit / EDC</option></select></div>
            <div class="field full"><label>Keterangan</label><input class="input" name="description" required maxlength="160" placeholder="Contoh: gas dan air galon dapur"></div>
            <div class="field full"><label>Foto nota</label><input class="input" type="file" name="attachment" accept="image/*,.pdf"><span class="hint">Wajib untuk tunai di bawah Rp 500.000.</span></div>
          </div>
        </div>
        <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalExpense')">Batal</button><button class="btn btn-primary">Simpan biaya</button></div>
      </form>
    </div>
  </div>

  <div class="modal-mask" id="modalRecurring">
    <div class="modal">
      <form method="POST" action="{{ route('purchase-orders.recurring.store') }}">
        @csrf
        <div class="modal-h"><div><h3>Template Biaya Rutin</h3><div class="sub">Dibuat sebagai draft PO setiap bulan pada tanggal yang dipilih.</div></div><button class="x" type="button" onclick="closeModal('modalRecurring')">✕</button></div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field full"><label>Nama biaya</label><input class="input" name="name" required maxlength="120" placeholder="Sewa lahan & bangunan"></div>
            <div class="field"><label>Kategori</label><select class="select" name="category" required>@foreach($expenseCategories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label>Nominal</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="1" step="1000" name="amount" required></div></div>
            <div class="field"><label>Tanggal generate</label><input class="input" type="number" min="1" max="28" name="day_of_month" value="1" required></div>
            <div class="field"><label>&nbsp;</label><label class="check-row"><input type="checkbox" name="is_active" value="1" checked> Aktif</label></div>
          </div>
        </div>
        <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalRecurring')">Batal</button><button class="btn btn-primary">Simpan template</button></div>
      </form>
    </div>
  </div>

  <div class="modal-mask" id="modalSupplier">
    <div class="modal">
      <form method="POST" action="{{ route('purchase-orders.suppliers.store') }}">
        @csrf
        <div class="modal-h"><div><h3>Supplier Baru</h3><div class="sub">Supplier bisa juga warung/toko langganan.</div></div><button class="x" type="button" onclick="closeModal('modalSupplier')">✕</button></div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Nama</label><input class="input" name="name" required maxlength="120"></div>
            <div class="field"><label>Kontak</label><input class="input" name="contact_person" maxlength="120"></div>
            <div class="field"><label>Telepon</label><input class="input" name="phone" maxlength="30"></div>
            <div class="field"><label>Kategori</label><input class="input" name="category" maxlength="60" placeholder="Minuman / Shuttlecock / Jasa"></div>
            <div class="field"><label>Termin</label><input class="input" name="payment_term" maxlength="60" placeholder="COD / tempo 14 hari"></div>
            <div class="field"><label>Alamat</label><input class="input" name="address" maxlength="300"></div>
            <div class="field full"><label>Catatan</label><textarea class="input" name="notes" rows="2" maxlength="500"></textarea></div>
          </div>
        </div>
        <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalSupplier')">Batal</button><button class="btn btn-primary">Simpan supplier</button></div>
      </form>
    </div>
  </div>
@endsection
