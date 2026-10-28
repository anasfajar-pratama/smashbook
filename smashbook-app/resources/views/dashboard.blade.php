@extends('layouts.app')

@section('title', 'Dashboard')
@section('crumb', 'Ringkasan operasional · ' . $date->locale('id')->translatedFormat('l, j F Y'))

@section('topbar-actions')
  <a class="btn btn-sm" href="{{ route('bookings.index') }}">Papan jadwal</a>
  @if(auth()->user()->isCashier())
    <a class="btn btn-primary btn-sm" href="{{ route('pos.index') }}">Buka POS</a>
  @endif
@endsection

@section('content')
  @if(auth()->user()->isOperator())
    <div class="grid g4">
      <div class="card kpi">
        <div class="row between"><span class="lbl">Okupansi lapangan</span><span class="ico blue">📊</span></div>
        <div class="val">{{ $occupancy }}%</div>
        <div class="row" style="gap:9px">
          <div class="bar" style="flex:1"><span style="width:{{ $occupancy }}%"></span></div>
          <span class="muted-text">{{ $booked_slots }}/{{ $available_slots }} slot</span>
        </div>
      </div>
      <div class="card kpi">
        <div class="row between"><span class="lbl">Booking hari ini</span><span class="ico brand">📅</span></div>
        <div class="val">{{ $today_bookings->whereNotIn('status', ['cancelled', 'expired', 'no_show'])->count() }}</div>
        <div class="dlt neutral">Jadwal aktif hari ini</div>
      </div>
      <div class="card kpi">
        <div class="row between"><span class="lbl">Hold aktif</span><span class="ico amber">⏳</span></div>
        <div class="val">{{ $today_bookings->where('status', 'held')->count() }}</div>
        <div class="dlt neutral">Slot ditahan sementara</div>
      </div>
      <div class="card kpi">
        <div class="row between"><span class="lbl">Stok menipis</span><span class="ico red">📦</span></div>
        <div class="val">{{ $low_stock_count }}</div>
        <div class="dlt neutral">Lihat daftar barang</div>
      </div>
    </div>

    <div class="alert info">
      <span class="ic">ℹ️</span>
      <div>Dashboard operator hanya menampilkan jadwal, okupansi, dan status stok. Data pendapatan, kas, dan laporan keuangan hanya untuk Kasir/Admin/Owner.</div>
    </div>
  @else
  <div class="grid g4">
    <div class="card kpi">
      <div class="row between">
        <span class="lbl">Pendapatan hari ini</span>
        <span class="ico brand">💰</span>
      </div>
      <div class="val">{{ rupiah($total_revenue) }}</div>
      <div class="dlt neutral">Sewa {{ rupiah($booking_revenue) }} · POS {{ rupiah($pos_revenue) }}</div>
    </div>

    <div class="card kpi">
      <div class="row between">
        <span class="lbl">Okupansi lapangan</span>
        <span class="ico blue">📊</span>
      </div>
      <div class="val">{{ $occupancy }}%</div>
      <div class="row" style="gap:9px">
        <div class="bar" style="flex:1"><span style="width:{{ $occupancy }}%"></span></div>
        <span class="muted-text">{{ $booked_slots }}/{{ $available_slots }} slot</span>
      </div>
    </div>

    <div class="card kpi">
      <div class="row between">
        <span class="lbl">Transaksi POS</span>
        <span class="ico violet">🧾</span>
      </div>
      <div class="val">{{ $pos_count }}</div>
      <div class="dlt neutral">ATV {{ rupiah($atv) }} · {{ $pos_items }} item terjual</div>
    </div>

    <div class="card kpi">
      <div class="row between">
        <span class="lbl">Selisih kas hari ini</span>
        <span class="ico {{ $cash_difference == 0 ? 'brand' : 'amber' }}">✅</span>
      </div>
      <div class="val {{ $cash_difference < 0 ? 'text-red' : '' }}">{{ rupiah($cash_difference) }}</div>
      <div class="dlt {{ $cash_difference == 0 ? 'up' : 'neutral' }}">
        {{ $cash_difference == 0 ? 'Seimbang / belum ada selisih' : 'Perlu ditinjau dari laporan kas' }}
      </div>
    </div>
  </div>
  @endif

  @if(! auth()->user()->isOperator())
  <div class="grid g-2-1">
    <div class="card">
      <div class="card-h">
        <div>
          <h3>Pendapatan 7 hari terakhir</h3>
          <div class="sub">Sewa lapangan vs penjualan POS</div>
        </div>
        <div class="right">
          <div class="legend">
            <span><i style="background:#10b981"></i>Sewa lapangan</span>
            <span><i style="background:#3b82f6"></i>POS</span>
          </div>
        </div>
      </div>
      <div class="card-b">
        @php $maxSeries = max(collect($series)->max(fn ($row) => max($row['booking'], $row['pos'])), 1); @endphp
        <div class="chart">
          @foreach($series as $row)
            <div class="col">
              <div class="bars">
                <i style="height:{{ max(round($row['booking'] / $maxSeries * 100), 2) }}%" title="Sewa {{ rupiah($row['booking']) }}"></i>
                <i class="pos" style="height:{{ max(round($row['pos'] / $maxSeries * 100), 2) }}%" title="POS {{ rupiah($row['pos']) }}"></i>
              </div>
              <div class="cap">{{ $row['label'] }}</div>
            </div>
          @endforeach
        </div>
        <div class="divider" style="margin:14px 0 12px"></div>
        <div class="grid g3" style="gap:12px">
          <div>
            <div class="muted-text">Total 7 hari</div>
            <div style="font-size:17px;font-weight:750">{{ rupiah(collect($series)->sum(fn ($row) => $row['booking'] + $row['pos'])) }}</div>
          </div>
          <div>
            <div class="muted-text">Sewa lapangan</div>
            <div style="font-size:17px;font-weight:750;color:#047857">{{ rupiah(collect($series)->sum('booking')) }}</div>
          </div>
          <div>
            <div class="muted-text">POS</div>
            <div style="font-size:17px;font-weight:750;color:#1d4ed8">{{ rupiah(collect($series)->sum('pos')) }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-h"><h3>Aktivitas terbaru</h3></div>
      <div class="card-b tight">
        @forelse($recent_audit as $log)
          @php
            $icon = match (true) {
              str_starts_with($log->action, 'booking') => '📅',
              str_starts_with($log->action, 'pos') => '🧾',
              str_starts_with($log->action, 'po') => '📝',
              str_starts_with($log->action, 'cash') => '💰',
              str_starts_with($log->action, 'stock') => '📦',
              str_starts_with($log->action, 'member') => '👤',
              default => '•',
            };
          @endphp
          <div class="list-item">
            <div class="ic ico {{ str_starts_with($log->action, 'cash') ? 'brand' : (str_starts_with($log->action, 'pos') ? 'violet' : 'blue') }}">{{ $icon }}</div>
            <div class="t">
              <b>{{ audit_label($log->action) }}</b>
              <small>{{ $log->user?->name ?? 'Sistem' }} · {{ $log->entity }}{{ $log->entity_id ? ' #' . $log->entity_id : '' }}</small>
            </div>
            <small class="muted-text nowrap">{{ $log->created_at?->diffForHumans() }}</small>
          </div>
        @empty
          <div class="empty"><span class="ic">📋</span>Belum ada aktivitas.</div>
        @endforelse
      </div>
    </div>
  </div>
  @endif

  <div class="grid g-2-1">
    <div class="card">
      <div class="card-h">
        <div>
          <h3>Jadwal lapangan hari ini</h3>
          <div class="sub">{{ $date->locale('id')->translatedFormat('l') }} · {{ $today_bookings->count() }} booking</div>
        </div>
        <div class="right"><a class="btn btn-sm" href="{{ route('bookings.index', ['date' => $date->toDateString()]) }}">Buka papan jadwal</a></div>
      </div>
      <div class="card-b tight">
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr><th>Jam</th><th>Lapangan</th><th>Penyewa</th><th>Status</th>@if(! auth()->user()->isOperator())<th class="num">Nilai</th>@endif<th></th></tr>
            </thead>
            <tbody>
              @forelse($today_bookings as $booking)
                <tr>
                  <td class="strong nowrap">{{ jam($booking->start_time) }}–{{ jam($booking->end_time) }}</td>
                  <td>{{ $booking->court?->name }}</td>
                  <td>
                    {{ $booking->customer_name }}
                    @if($booking->member)<span class="badge green">Member</span>@endif
                  </td>
                  <td>
                    @php
                      $badge = match($booking->status) {
                        'checked_in' => 'blue',
                        'completed' => 'green',
                        'held' => 'amber',
                        'cancelled' => 'grey',
                        'no_show' => 'red',
                        default => $booking->payment_status === 'paid' ? 'green' : ($booking->payment_status === 'partial' ? 'amber' : 'red'),
                      };
                      $label = match($booking->status) {
                        'checked_in' => 'Checked-in',
                        'completed' => 'Selesai',
                        'held' => 'Hold',
                        'cancelled' => 'Batal',
                        'no_show' => 'No-show',
                        default => $booking->payment_status === 'paid' ? 'Lunas' : ($booking->payment_status === 'partial' ? 'DP' : 'Belum bayar'),
                      };
                    @endphp
                    <span class="badge {{ $badge }}">{{ $label }}</span>
                  </td>
                  @if(! auth()->user()->isOperator())<td class="num strong">{{ rupiah($booking->total_amount) }}</td>@endif
                  <td class="right">
                    @if(in_array($booking->status, ['confirmed', 'checked_in']))
                      <form method="POST" action="{{ $booking->status === 'checked_in' ? route('bookings.checkout', $booking) : route('bookings.checkin', $booking) }}">
                        @csrf
                        <button class="btn btn-sm">{{ $booking->status === 'checked_in' ? 'Check-out' : 'Check-in' }}</button>
                      </form>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="{{ auth()->user()->isOperator() ? 5 : 6 }}"><div class="empty"><span class="ic">📅</span>Belum ada booking hari ini.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-h">
          <h3>⚠️ Stok menipis</h3>
          <div class="right">
            @if(auth()->user()->isAdmin())
              <a class="btn btn-sm btn-primary" href="{{ route('purchase-orders.index', ['tab' => 'quick']) }}">Quick PO</a>
            @endif
          </div>
        </div>
        <div class="card-b tight">
          @forelse($low_stock as $product)
            <div class="list-item">
              <div class="ic ico red">{{ $product->icon ?: '📦' }}</div>
              <div class="t"><b>{{ $product->name }}</b><small>{{ $product->stock_qty }} {{ $product->base_unit }} · minimum {{ $product->min_stock }}</small></div>
              <span class="badge {{ $product->stock_qty <= 0 ? 'red' : 'amber' }}">{{ $product->stock_qty <= 0 ? 'Habis' : 'Kurang' }}</span>
            </div>
          @empty
            <div class="empty"><span class="ic">✅</span>Semua stok di atas minimum.</div>
          @endforelse
        </div>
      </div>

      @if(auth()->user()->isAdmin())
        <div class="card">
          <div class="card-h">
            <h3>PO perlu tindakan</h3>
            <div class="right"><span class="badge amber">{{ $pending_pos->count() }} dokumen</span></div>
          </div>
          <div class="card-b tight">
            @forelse($pending_pos as $po)
              <div class="list-item">
                <div class="ic ico amber">📝</div>
                <div class="t"><b>{{ $po->code }}</b><small>{{ $po->supplier_name ?: $po->supplier?->name }} · {{ $po->status === 'submitted' ? 'menunggu approval' : 'siap diterima' }}</small></div>
                <div class="right">
                  <div class="strong">{{ rupiah($po->total_amount) }}</div>
                  <a class="btn btn-sm" href="{{ route('purchase-orders.index', ['tab' => 'list']) }}">Tinjau</a>
                </div>
              </div>
            @empty
              <div class="empty"><span class="ic">✅</span>Tidak ada PO menunggu.</div>
            @endforelse
          </div>
        </div>
      @endif

      <div class="card">
        <div class="card-h"><h3>Perlu perhatian</h3></div>
        <div class="card-b stack">
          <div class="list-item" style="padding:0;border:0">
            <div class="ic ico amber">⏳</div>
            <div class="t"><b>{{ $expiring_members }} member akan kedaluwarsa</b><small>Dalam 7 hari ke depan — siapkan pesan WhatsApp.</small></div>
            @if(auth()->user()->isCashier())
              <a class="btn btn-sm" href="{{ route('members.index', ['status' => 'akan_expired']) }}">Lihat</a>
            @endif
          </div>
          @if($active_session)
            <div class="divider"></div>
            <div class="list-item" style="padding:0;border:0">
              <div class="ic ico brand">💰</div>
              <div class="t"><b>Shift {{ ucfirst($active_session->shift) }} aktif</b><small>Kas seharusnya {{ rupiah($active_session->expectedBalance()) }}</small></div>
              <a class="btn btn-sm" href="{{ route('cash.index') }}">Buka kas</a>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
