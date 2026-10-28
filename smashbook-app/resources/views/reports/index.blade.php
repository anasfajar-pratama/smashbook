@extends('layouts.app')

@section('title', 'Laporan')
@section('crumb', 'Analisis operasional & keuangan · ' . $from->locale('id')->translatedFormat('F Y'))

@section('topbar-actions')
  <button class="btn btn-sm" type="button" onclick="window.print()">Cetak</button>
@endsection

@section('content')
  @php
    $tabLinks = [
      'pendapatan' => 'Pendapatan',
      'okupansi' => 'Okupansi Lapangan',
      'kas' => 'Kas & Selisih',
      'stok' => 'Stok & Laba POS',
      'member' => 'Member',
      'labarugi' => 'Laba Rugi',
    ];
    $expenseLabels = ['listrik' => 'Listrik & air', 'air' => 'Air', 'gaji' => 'Gaji & upah', 'sewa' => 'Sewa lahan', 'perawatan' => 'Perawatan', 'kebersihan' => 'Kebersihan', 'internet' => 'Internet & CCTV', 'lain_lain' => 'Lain-lain'];
  @endphp

  <div class="card">
    <div class="card-b">
      <form method="GET" action="{{ route('reports.index') }}" class="filter-bar">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="field" style="min-width:150px"><label>Dari tanggal</label><input class="input" type="date" name="from" value="{{ $from->toDateString() }}"></div>
        <div class="field" style="min-width:150px"><label>s/d tanggal</label><input class="input" type="date" name="to" value="{{ $to->toDateString() }}"></div>
        <div class="field"><label>&nbsp;</label><button class="btn btn-dark">Terapkan</button></div>
        <div class="spacer"></div>
        <span class="muted-text">{{ $from->locale('id')->translatedFormat('j M Y') }} – {{ $to->locale('id')->translatedFormat('j M Y') }}</span>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="tabs-bar">
      @foreach($tabLinks as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }}</a>
      @endforeach
    </div>

    @if($tab === 'pendapatan')
      <div class="card-b stack">
        <div class="grid g4">
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Total pendapatan</span><div class="val">{{ rupiah($data['total']) }}</div><div class="dlt neutral">{{ $data['booking_count'] + $data['pos_count'] + $data['membership_count'] }} transaksi</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Sewa lapangan</span><div class="val text-green" style="font-size:19px">{{ rupiah($data['booking_total']) }}</div><div class="dlt neutral">{{ $data['booking_count'] }} booking</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">POS</span><div class="val text-blue" style="font-size:19px">{{ rupiah($data['pos_total']) }}</div><div class="dlt neutral">{{ $data['pos_count'] }} transaksi</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Membership</span><div class="val" style="font-size:19px;color:#6d28d9">{{ rupiah($data['membership_total']) }}</div><div class="dlt neutral">{{ $data['membership_count'] }} paket</div></div>
        </div>

        @php
          $totalRev = max($data['total'], 1);
          $maxDay = max(collect($data['days'])->max(fn ($d) => $d['total']), 1);
        @endphp
        <div class="grid g3">
          <div><div class="row between"><span class="muted-text">Sewa lapangan</span><span class="strong">{{ round($data['booking_total'] / $totalRev * 100) }}%</span></div><div class="bar" style="margin-top:4px"><span style="width:{{ round($data['booking_total'] / $totalRev * 100) }}%"></span></div></div>
          <div><div class="row between"><span class="muted-text">POS</span><span class="strong">{{ round($data['pos_total'] / $totalRev * 100) }}%</span></div><div class="bar" style="margin-top:4px"><span class="blue" style="width:{{ round($data['pos_total'] / $totalRev * 100) }}%"></span></div></div>
          <div><div class="row between"><span class="muted-text">Membership</span><span class="strong">{{ round($data['membership_total'] / $totalRev * 100) }}%</span></div><div class="bar" style="margin-top:4px"><span class="amber" style="width:{{ round($data['membership_total'] / $totalRev * 100) }}%"></span></div></div>
        </div>

        <div class="card" style="box-shadow:none">
          <div class="card-h"><h3>Pendapatan harian</h3><div class="right"><div class="legend"><span><i style="background:#10b981"></i>Sewa</span><span><i style="background:#3b82f6"></i>POS</span><span><i style="background:#8b5cf6"></i>Membership</span></div></div></div>
          <div class="card-b">
            <div class="chart">
              @foreach($data['days'] as $day)
                <div class="col" title="{{ tanggal($day['date']) }} · {{ rupiah($day['total']) }}">
                  <div class="bars">
                    <i style="height:{{ max(round($day['booking'] / $maxDay * 100), 2) }}%"></i>
                    <i class="pos" style="height:{{ max(round($day['pos'] / $maxDay * 100), 2) }}%"></i>
                    <i style="background:#8b5cf6;height:{{ max(round($day['membership'] / $maxDay * 100), 2) }}%"></i>
                  </div>
                  <div class="cap">{{ $day['date']->format('j') }}</div>
                </div>
              @endforeach
            </div>
          </div>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Tanggal</th><th class="num">Sewa lapangan</th><th class="num">POS</th><th class="num">Membership</th><th class="num">Total</th></tr></thead>
            <tbody>
              @foreach($data['days'] as $day)
                <tr>
                  <td>{{ tanggal($day['date'], 'l, j M Y') }}</td>
                  <td class="num">{{ rupiah($day['booking']) }}</td>
                  <td class="num">{{ rupiah($day['pos']) }}</td>
                  <td class="num">{{ rupiah($day['membership']) }}</td>
                  <td class="num strong">{{ rupiah($day['total']) }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr><td class="strong">Total</td><td class="num strong">{{ rupiah($data['booking_total']) }}</td><td class="num strong">{{ rupiah($data['pos_total']) }}</td><td class="num strong">{{ rupiah($data['membership_total']) }}</td><td class="num strong">{{ rupiah($data['total']) }}</td></tr>
            </tfoot>
          </table>
        </div>
      </div>
    @endif

    @if($tab === 'okupansi')
      <div class="card-b stack">
        <div class="grid g4">
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Okupansi rata-rata</span><div class="val">{{ $data['occupancy'] }}%</div><div class="bar" style="margin-top:4px"><span style="width:{{ $data['occupancy'] }}%"></span></div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Jam tersedia</span><div class="val" style="font-size:20px">{{ angka($data['available_hours']) }} jam</div><div class="dlt neutral">Jam operasional × lapangan × hari</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Jam terpakai</span><div class="val" style="font-size:20px">{{ angka($data['booked_hours']) }} jam</div><div class="dlt neutral">Slot booking aktif</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Pendapatan per jam</span><div class="val" style="font-size:20px">{{ rupiah($data['revenue_per_hour']) }}</div><div class="dlt neutral">Dari booking_slots</div></div>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Slot jam</th><th class="num">Slot terpakai</th><th class="num">Slot tersedia</th><th class="num">Okupansi</th><th class="num">Pendapatan</th><th>Catatan</th></tr></thead>
            <tbody>
              @foreach($data['per_hour'] as $row)
                <tr>
                  <td class="strong">{{ $row['hour'] }}–{{ \Carbon\Carbon::parse($row['hour'])->addHour()->format('H:i') }}</td>
                  <td class="num">{{ $row['booked'] }}</td>
                  <td class="num">{{ $row['available'] }}</td>
                  <td class="num">{{ $row['occupancy'] }}%</td>
                  <td class="num">{{ rupiah($row['revenue']) }}</td>
                  <td>
                    @if($row['occupancy'] >= 90)<span class="badge red">Hampir penuh</span>
                    @elseif($row['occupancy'] <= 30)<span class="badge amber">Sepi</span>
                    @else<span class="badge grey">Normal</span>@endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="grid g2">
          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Okupansi per lapangan</h3></div>
            <div class="card-b stack">
              @foreach($data['per_court'] as $row)
                <div>
                  <div class="row between"><span class="strong">{{ $row['court']->name }}</span><span>{{ $row['occupancy'] }}% · {{ angka($row['hours']) }} jam</span></div>
                  <div class="bar" style="margin-top:4px"><span class="{{ $row['occupancy'] < 50 ? 'amber' : '' }}" style="width:{{ $row['occupancy'] }}%"></span></div>
                </div>
              @endforeach
            </div>
          </div>
          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Rekomendasi</h3></div>
            <div class="card-b stack">
              @if($data['busiest'])
                <div class="alert err"><span class="ic">🔥</span><div><b>Jam tersibuk {{ $data['busiest']['hour'] }}</b> ({{ $data['busiest']['occupancy'] }}%). Pertimbangkan menaikkan tarif prime atau menambah kapasitas.</div></div>
              @endif
              @if($data['quietest'])
                <div class="alert warn"><span class="ic">💡</span><div><b>Jam tersepi {{ $data['quietest']['hour'] }}</b> ({{ $data['quietest']['occupancy'] }}%). Kandidat promo jam sepi.</div></div>
              @endif
              <div class="note-box">Occupancy dihitung dari slot 30 menit yang terisi booking aktif, dibagi total slot tersedia pada jam operasional.</div>
            </div>
          </div>
        </div>
      </div>
    @endif

    @if($tab === 'kas')
      <div class="card-b stack">
        <div class="grid g4">
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Tunai diterima</span><div class="val text-green" style="font-size:20px">{{ rupiah($data['cash_in']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Tunai keluar</span><div class="val text-red" style="font-size:20px">{{ rupiah($data['cash_out']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Non-tunai</span><div class="val text-blue" style="font-size:20px">{{ rupiah($data['non_cash']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Selisih kumulatif</span><div class="val {{ $data['difference'] == 0 ? 'text-green' : 'text-red' }}" style="font-size:20px">{{ rupiah($data['difference']) }}</div></div>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Petugas</th><th class="num">Jumlah shift</th><th class="num">Tunai masuk</th><th class="num">Selisih kumulatif</th><th>Status</th></tr></thead>
            <tbody>
              @forelse($data['by_cashier'] as $row)
                <tr>
                  <td class="strong">{{ $row['cashier']?->name ?? '—' }}</td>
                  <td class="num">{{ $row['shifts'] }}</td>
                  <td class="num">{{ rupiah($row['cash_in']) }}</td>
                  <td class="num {{ $row['difference'] < 0 ? 'text-red' : ($row['difference'] > 0 ? 'text-green' : '') }}">{{ rupiah($row['difference']) }}</td>
                  <td><span class="badge {{ abs($row['difference']) <= 10000 ? 'green' : 'amber' }}">{{ abs($row['difference']) <= 10000 ? 'Bersih' : 'Perlu perhatian' }}</span></td>
                </tr>
              @empty
                <tr><td colspan="5"><div class="empty">Belum ada sesi pada periode ini.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Kode sesi</th><th>Tanggal</th><th>Shift</th><th>Petugas</th><th class="num">Seharusnya</th><th class="num">Fisik</th><th class="num">Selisih</th><th>Status</th></tr></thead>
            <tbody>
              @forelse($data['sessions'] as $session)
                <tr>
                  <td class="mono">{{ $session->code }}</td>
                  <td>{{ tanggal($session->opened_at) }}</td>
                  <td>{{ ucfirst($session->shift) }}</td>
                  <td>{{ $session->cashier?->name }}</td>
                  <td class="num">{{ rupiah($session->expected_balance ?? $session->expectedBalance()) }}</td>
                  <td class="num">{{ $session->physical_balance !== null ? rupiah($session->physical_balance) : '—' }}</td>
                  <td class="num {{ (float) $session->difference < 0 ? 'text-red' : '' }}">{{ $session->difference !== null ? rupiah($session->difference) : '—' }}</td>
                  <td><span class="badge {{ $session->status === 'open' ? 'amber' : 'green' }}">{{ ucfirst($session->status) }}</span></td>
                </tr>
              @empty
                <tr><td colspan="8"><div class="empty">Belum ada sesi.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="grid g3">
          @foreach(['qris' => 'QRIS', 'transfer' => 'Transfer', 'debit' => 'Debit/EDC'] as $key => $label)
            <div class="note-box"><b>{{ $label }}</b><br>{{ rupiah($data['non_cash_by_method'][$key] ?? 0) }}</div>
          @endforeach
        </div>
      </div>
    @endif

    @if($tab === 'stok')
      <div class="card-b stack">
        <div class="grid g4">
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Nilai persediaan akhir</span><div class="val" style="font-size:20px">{{ rupiah($data['stock_value']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Pembelian diterima</span><div class="val" style="font-size:20px">{{ rupiah($data['purchases']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">HPP POS</span><div class="val" style="font-size:20px">{{ rupiah($data['pos_cost']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Laba kotor POS</span><div class="val text-green" style="font-size:20px">{{ rupiah($data['pos_profit']) }}</div></div>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Kategori</th><th class="num">Penjualan</th><th class="num">HPP</th><th class="num">Laba kotor</th><th class="num">Margin</th></tr></thead>
            <tbody>
              @forelse($data['categories'] as $row)
                <tr><td class="strong">{{ $row->category }}</td><td class="num">{{ rupiah($row->sales) }}</td><td class="num">{{ rupiah($row->cost) }}</td><td class="num text-green">{{ rupiah($row->profit) }}</td><td class="num">{{ number_format($row->margin, 0, ',', '.') }}%</td></tr>
              @empty
                <tr><td colspan="5"><div class="empty">Belum ada penjualan POS.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="grid g-2-1">
          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Barang terlaris</h3></div>
            <div class="table-wrap">
              <table class="tbl">
                <thead><tr><th>Barang</th><th class="num">Qty</th><th class="num">Penjualan</th><th class="num">Laba</th><th class="num">Sisa stok</th></tr></thead>
                <tbody>
                  @forelse($data['top_products'] as $row)
                    <tr><td class="strong">{{ $row->name }}</td><td class="num">{{ angka($row->qty) }} {{ $row->base_unit }}</td><td class="num">{{ rupiah($row->sales) }}</td><td class="num text-green">{{ rupiah($row->profit) }}</td><td class="num {{ $row->stock_qty <= 0 ? 'text-red' : '' }}">{{ $row->stock_qty }}</td></tr>
                  @empty
                    <tr><td colspan="5"><div class="empty">Belum ada data.</div></td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Stok menipis</h3></div>
            <div class="card-b tight">
              @forelse($data['low_stock'] as $product)
                <div class="list-item"><div class="ic ico red">{{ $product->icon ?: '📦' }}</div><div class="t"><b>{{ $product->name }}</b><small>sisa {{ $product->stock_qty }} · min {{ $product->min_stock }}</small></div><span class="badge red">Beli</span></div>
              @empty
                <div class="empty"><span class="ic">✅</span>Semua aman.</div>
              @endforelse
            </div>
          </div>
        </div>
      </div>
    @endif

    @if($tab === 'member')
      <div class="card-b stack">
        <div class="grid g4">
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Member aktif</span><div class="val">{{ $data['active_members'] }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Member baru</span><div class="val text-green" style="font-size:20px">{{ $data['new_members'] }}</div><div class="dlt neutral">Periode ini</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Churn</span><div class="val text-red" style="font-size:20px">{{ $data['churn'] }}</div><div class="dlt neutral">Membership berakhir</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Pendapatan membership</span><div class="val" style="font-size:20px">{{ rupiah($data['membership_revenue']) }}</div><div class="dlt neutral">Paket baru periode ini</div></div>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Paket</th><th class="num">Member</th><th class="num">Pendapatan paket</th><th class="num">Perpanjangan</th><th class="num">Diskon</th></tr></thead>
            <tbody>
              @foreach($data['per_type'] as $row)
                <tr><td class="strong">{{ $row['type']->name }}</td><td class="num">{{ $row['members'] }}</td><td class="num">{{ rupiah($row['revenue']) }}</td><td class="num">{{ $row['renewals'] }}</td><td class="num">{{ (int) $row['type']->discount_pct }}%</td></tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="grid g2">
          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Member paling aktif</h3></div>
            <div class="card-b tight">
              @forelse($data['top_members'] as $member)
                <div class="list-item"><div class="avatar-sm green">{{ mb_substr($member->name, 0, 1) }}</div><div class="t"><b>{{ $member->name }}</b><small>{{ $member->bookings_count }} booking · belanja {{ rupiah($member->spent) }}</small></div><span class="badge grey">{{ $member->code }}</span></div>
              @empty
                <div class="empty">Belum ada data.</div>
              @endforelse
            </div>
          </div>
          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Perlu tindak lanjut</h3></div>
            <div class="card-b tight">
              @forelse($data['expiring'] as $member)
                <div class="list-item"><div class="ic ico amber">⏳</div><div class="t"><b>{{ $member->name }}</b><small>aktif sampai {{ $member->activeSubscription ? tanggal($member->activeSubscription->end_date) : '—' }}</small></div><a class="btn btn-sm" href="{{ route('members.index', ['status' => 'akan_expired']) }}">Tinjau</a></div>
              @empty
                <div class="empty"><span class="ic">✅</span>Tidak ada member akan habis.</div>
              @endforelse
            </div>
          </div>
        </div>
      </div>
    @endif

    @if($tab === 'labarugi')
      <div class="card-b stack">
        <div class="row between">
          <div><h3>Laba Rugi Sederhana</h3><div class="muted-text">{{ $from->locale('id')->translatedFormat('F Y') }} · basis kas sederhana</div></div>
          <span class="badge dark">Belum diaudit</span>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Komponen</th><th class="num">Nominal</th><th class="num">% Pendapatan</th></tr></thead>
            <tbody>
              <tr style="background:#f8fafc"><td class="strong">PENDAPATAN</td><td></td><td></td></tr>
              <tr><td style="padding-left:28px">Sewa lapangan</td><td class="num">{{ rupiah($data['revenue']['booking_total']) }}</td><td class="num">{{ $data['revenue']['total'] > 0 ? number_format($data['revenue']['booking_total'] / $data['revenue']['total'] * 100, 1, ',', '.') : 0 }}%</td></tr>
              <tr><td style="padding-left:28px">POS</td><td class="num">{{ rupiah($data['revenue']['pos_total']) }}</td><td class="num">{{ $data['revenue']['total'] > 0 ? number_format($data['revenue']['pos_total'] / $data['revenue']['total'] * 100, 1, ',', '.') : 0 }}%</td></tr>
              <tr><td style="padding-left:28px">Membership &amp; komunitas</td><td class="num">{{ rupiah($data['revenue']['membership_total']) }}</td><td class="num">{{ $data['revenue']['total'] > 0 ? number_format($data['revenue']['membership_total'] / $data['revenue']['total'] * 100, 1, ',', '.') : 0 }}%</td></tr>
              <tr style="background:#f8fafc"><td class="strong">Total pendapatan</td><td class="num strong">{{ rupiah($data['revenue']['total']) }}</td><td class="num strong">100%</td></tr>

              <tr style="background:#f8fafc"><td class="strong">HARGA POKOK PENJUALAN</td><td></td><td></td></tr>
              <tr><td style="padding-left:28px">HPP barang POS</td><td class="num text-red">− {{ rupiah($data['hpp']) }}</td><td class="num">{{ $data['revenue']['total'] > 0 ? number_format($data['hpp'] / $data['revenue']['total'] * 100, 1, ',', '.') : 0 }}%</td></tr>
              <tr style="background:#ecfdf5"><td class="strong">Laba kotor</td><td class="num strong text-green">{{ rupiah($data['gross_profit']) }}</td><td class="num strong">{{ number_format($data['gross_margin'], 1, ',', '.') }}%</td></tr>

              <tr style="background:#f8fafc"><td class="strong">BIAYA OPERASIONAL</td><td></td><td></td></tr>
              @forelse($data['expenses'] as $expense)
                <tr><td style="padding-left:28px">{{ $expenseLabels[$expense->category] ?? ucfirst($expense->category) }}</td><td class="num text-red">− {{ rupiah($expense->amount) }}</td><td class="num">{{ $data['revenue']['total'] > 0 ? number_format($expense->amount / $data['revenue']['total'] * 100, 1, ',', '.') : 0 }}%</td></tr>
              @empty
                <tr><td style="padding-left:28px" class="muted-text">Belum ada biaya tercatat</td><td></td><td></td></tr>
              @endforelse
              <tr style="background:#f8fafc"><td class="strong">Total biaya operasional</td><td class="num strong text-red">− {{ rupiah($data['total_expenses']) }}</td><td class="num strong">{{ $data['revenue']['total'] > 0 ? number_format($data['total_expenses'] / $data['revenue']['total'] * 100, 1, ',', '.') : 0 }}%</td></tr>

              <tr style="background:#ecfdf5"><td class="strong" style="font-size:15px">LABA BERSIH</td><td class="num strong text-green" style="font-size:16px">{{ rupiah($data['net_profit']) }}</td><td class="num strong" style="font-size:15px">{{ number_format($data['net_margin'], 1, ',', '.') }}%</td></tr>
            </tbody>
          </table>
        </div>

        <div class="grid g4">
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Laba kotor</span><div class="val text-green" style="font-size:19px">{{ rupiah($data['gross_profit']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Total biaya</span><div class="val text-red" style="font-size:19px">{{ rupiah($data['total_expenses']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Laba bersih</span><div class="val text-green" style="font-size:19px">{{ rupiah($data['net_profit']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Margin bersih</span><div class="val" style="font-size:19px">{{ number_format($data['net_margin'], 1, ',', '.') }}%</div></div>
        </div>

        <div class="alert info">
          <span class="ic">ℹ️</span>
          <div>Laporan ini adalah laba-rugi sederhana berbasis kas. Belum memasukkan penyusutan, pajak, atau akrual. Gunakan sebagai indikator operasional, bukan laporan akuntansi formal.</div>
        </div>
      </div>
    @endif
  </div>
@endsection
