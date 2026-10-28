@extends('layouts.app')

@section('title', 'Booking Lapangan')
@section('crumb', 'Papan jadwal · ' . $date->locale('id')->translatedFormat('l, j F Y'))

@section('topbar-actions')
  <button class="btn btn-primary btn-sm" onclick="openModal('modalBooking')">+ Booking Baru</button>
@endsection

@section('content')
  @php
    $tabLinks = [
      'board' => 'Papan Jadwal',
      'list' => 'Daftar Booking',
      'recurring' => 'Booking Berulang',
      'policy' => 'Kebijakan',
    ];
    $statusLabels = [
      'held' => ['Hold', 'amber'],
      'confirmed' => ['Confirmed', 'blue'],
      'checked_in' => ['Checked-in', 'blue'],
      'completed' => ['Selesai', 'green'],
      'cancelled' => ['Batal', 'grey'],
      'no_show' => ['No-show', 'red'],
      'expired' => ['Expired', 'grey'],
    ];
    $dayType = $pricing->dayType($date);
    $ruleDayType = $dayType === 'weekend' ? 'weekend' : 'weekday';
  @endphp

  <div class="card">
    <div class="card-b">
      <form method="GET" action="{{ route('bookings.index') }}" class="filter-bar">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="field" style="min-width:150px">
          <label>Tanggal</label>
          <input class="input" type="date" name="date" value="{{ $date->toDateString() }}">
        </div>
        <div class="field" style="min-width:170px">
          <label>Lapangan</label>
          <select class="select" name="court">
            <option value="">Semua lapangan</option>
            @foreach($courts as $court)
              <option value="{{ $court->id }}" @selected($courtFilter === $court->id)>{{ $court->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field" style="min-width:170px">
          <label>Status</label>
          <select class="select" name="status">
            <option value="">Semua status</option>
            @foreach($statusLabels as $key => $meta)
              <option value="{{ $key }}" @selected($statusFilter === $key)>{{ $meta[0] }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label>&nbsp;</label>
          <div class="row">
            <button class="btn">Terapkan</button>
            <a class="btn" href="{{ route('bookings.index', ['date' => $date->copy()->subDay()->toDateString(), 'tab' => $tab]) }}">‹</a>
            <a class="btn" href="{{ route('bookings.index', ['date' => now()->toDateString(), 'tab' => $tab]) }}">Hari ini</a>
            <a class="btn" href="{{ route('bookings.index', ['date' => $date->copy()->addDay()->toDateString(), 'tab' => $tab]) }}">›</a>
          </div>
        </div>
        <div class="spacer"></div>
        <div class="badge {{ $dayType === 'weekend' ? 'blue' : 'grey' }}" style="align-self:flex-end">
          {{ $dayType === 'weekend' ? 'Weekend / hari libur' : 'Hari kerja' }}
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="tabs-bar">
      @foreach($tabLinks as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }}</a>
      @endforeach
    </div>

    @if($tab === 'board')
      <div class="card-b stack">
        <div class="row wrap">
          <div class="legend">
            <span><i style="background:#ecfdf5;border:1px solid #a7f3d0"></i>Member</span>
            <span><i style="background:#eff6ff;border:1px solid #bfdbfe"></i>Umum</span>
            <span><i style="background:#fffbeb;border:1px solid #fde68a"></i>Hold</span>
            <span><i style="background:#f8fafc;border:1px solid #e2e8f0"></i>Tersedia</span>
          </div>
          <div class="spacer"></div>
          <span class="muted-text">Klik slot kosong untuk membuat booking. Tarif mengikuti master lapangan.</span>
        </div>

        <div class="table-wrap">
          <div class="board" style="grid-template-columns:74px repeat({{ max($courts->when($courtFilter, fn ($c) => $c->where('id', $courtFilter))->count(), 1) }}, minmax(120px,1fr));">
            <div class="hd">Jam</div>
            @foreach($courts->when($courtFilter, fn ($c) => $c->where('id', $courtFilter)) as $court)
              <div class="hd">{{ $court->name }}</div>
            @endforeach

            @foreach($board as $row)
              <div class="tm {{ $row['peak'] ? 'peak-row' : '' }}">{{ $row['hour'] }}</div>
              @foreach($row['cells'] as $cell)
                @php
                  $court = $cell['court'];
                  $booking = $cell['booking'];
                  $hourMinutes = $pricing->toMinutes($row['hour']);
                  $rule = $court->priceRules->first(function ($candidate) use ($hourMinutes, $ruleDayType, $pricing) {
                      return in_array($candidate->day_type, [$ruleDayType, 'all'], true)
                          && $hourMinutes >= $pricing->toMinutes($candidate->start_time)
                          && $hourMinutes < $pricing->toMinutes($candidate->end_time);
                  });
                @endphp
                <div class="slot">
                  @if($booking)
                    @php
                      $class = $booking->status === 'held' ? 'hold' : ($booking->member_id ? 'mb' : 'bk');
                      $statusLabel = $statusLabels[$booking->status][0] ?? ucfirst($booking->status);
                      $remaining = max((float) $booking->total_amount - (float) $booking->paid_amount, 0);
                      $canPay = $booking->status === 'held' && $remaining > 0 && ! auth()->user()->isOperator();
                    @endphp
                    @if($canPay)
                      <button class="cell {{ $class }}" type="button" style="width:100%;text-align:left;cursor:pointer"
                        title="Klik untuk mencatat pembayaran {{ $booking->code }}"
                        onclick="openPayment('{{ route('bookings.payment', $booking) }}', '{{ $booking->code }}', {{ $remaining }})">
                        <b>{{ \Illuminate\Support\Str::limit($booking->customer_name, 20) }}</b>
                        <small>{{ $statusLabel }} · {{ jam($booking->start_time) }}–{{ jam($booking->end_time) }} · Bayar</small>
                      </button>
                    @else
                      <div class="cell {{ $class }}" title="{{ $booking->code }} · {{ $booking->customer_name }}">
                        <b>{{ \Illuminate\Support\Str::limit($booking->customer_name, 20) }}</b>
                        <small>{{ $statusLabel }} · {{ jam($booking->start_time) }}–{{ jam($booking->end_time) }}</small>
                      </div>
                    @endif
                  @elseif(! $court->is_active)
                    <div class="cell peak-locked"><b>Nonaktif</b><small>—</small></div>
                  @else
                    <button class="cell" type="button" style="width:100%;text-align:left" onclick="pickSlot({{ $court->id }}, '{{ $row['hour'] }}')">
                      <b>Tersedia</b>
                      <small>{{ $rule ? rupiah($rule->price_per_hour) . '/jam' : 'Tarif belum diatur' }}</small>
                    </button>
                  @endif
                </div>
              @endforeach
            @endforeach
          </div>
        </div>

        <div class="grid g4">
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Okupansi hari ini</span><div class="val">{{ $stats['occupancy'] }}%</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Nilai booking aktif</span><div class="val" style="font-size:20px">{{ rupiah($stats['confirmed_value']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Belum dibayar</span><div class="val text-amber" style="font-size:20px">{{ rupiah($stats['unpaid_value']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Hold aktif</span><div class="val" style="font-size:20px">{{ $stats['hold_count'] }} slot</div></div>
        </div>
      </div>
    @endif

    @if($tab === 'list')
      <div class="card-b tight">
        <div class="card-b" style="padding-bottom:0">
          <div class="stat-inline">
            <div class="item"><span class="l">Total booking</span><span class="v">{{ $bookings->count() }}</span></div>
            <div class="item"><span class="l">Nilai</span><span class="v">{{ rupiah($bookings->sum('total_amount')) }}</span></div>
            <div class="item"><span class="l">Dibayar</span><span class="v text-green">{{ rupiah($bookings->sum('paid_amount')) }}</span></div>
            <div class="item"><span class="l">Sisa</span><span class="v text-amber">{{ rupiah($bookings->sum(fn ($b) => max((float) $b->total_amount - (float) $b->paid_amount, 0))) }}</span></div>
            <div class="spacer" style="flex:1"></div>
            <button class="btn btn-sm" onclick="copyText('{{ $date->toDateString() }}', 'Tanggal')">Salin tanggal</button>
          </div>
        </div>
        <div class="table-wrap" style="margin-top:12px">
          <table class="tbl">
            <thead>
              <tr>
                <th>Kode</th><th>Penyewa</th><th>Lapangan</th><th>Jadwal</th><th>Tipe</th>
                <th class="num">Total</th><th class="num">Dibayar</th><th>Status</th><th></th>
              </tr>
            </thead>
            <tbody>
              @forelse($bookings as $booking)
                @php
                  $remaining = max((float) $booking->total_amount - (float) $booking->paid_amount, 0);
                  $statusMeta = $statusLabels[$booking->status] ?? [ucfirst($booking->status), 'grey'];
                  $waText = 'Halo ' . $booking->customer_name . ', booking ' . $booking->code . ' di ' . ($booking->court?->name ?? 'lapangan') . ' pada ' . tanggal($booking->booking_date) . ' pukul ' . jam($booking->start_time) . '–' . jam($booking->end_time) . '. Total ' . rupiah($booking->total_amount) . ', status ' . $statusMeta[0] . '. Terima kasih — ' . setting('venue_name', 'Smash Arena') . '.';
                @endphp
                <tr>
                  <td class="mono">{{ $booking->code }}</td>
                  <td>
                    <div class="strong">{{ $booking->customer_name }}</div>
                    <div class="muted-text">{{ $booking->customer_phone ?: ($booking->member?->code ?? '—') }}</div>
                  </td>
                  <td>{{ $booking->court?->name }}</td>
                  <td class="nowrap">{{ tanggal($booking->booking_date) }}<br><span class="muted-text">{{ jam($booking->start_time) }}–{{ jam($booking->end_time) }}</span></td>
                  <td>
                    @if($booking->member)
                      <span class="badge green">Member</span>
                    @else
                      <span class="badge grey">Umum</span>
                    @endif
                  </td>
                  <td class="num strong">
                    @if((float) $booking->discount_amount > 0)
                      <span class="strike">{{ rupiah((float) $booking->base_amount) }}</span><br>
                    @endif
                    {{ rupiah($booking->total_amount) }}
                  </td>
                  <td class="num">{{ rupiah($booking->paid_amount) }}</td>
                  <td>
                    <span class="badge {{ $statusMeta[1] }}">{{ $statusMeta[0] }}</span>
                    @if($booking->payment_status === 'unpaid')<span class="badge amber">Belum bayar</span>@endif
                    @if($booking->payment_status === 'partial')<span class="badge amber">DP</span>@endif
                    @if($booking->payment_status === 'paid')<span class="badge green">Lunas</span>@endif
                  </td>
                  <td>
                    <div class="tbl-actions">
                      <button class="btn btn-sm" type="button" data-copy="{{ $waText }}">WA</button>
                      @if($remaining > 0 && in_array($booking->status, ['confirmed', 'checked_in', 'held']) && ! auth()->user()->isOperator())
                        <button class="btn btn-sm" type="button" onclick="openPayment('{{ route('bookings.payment', $booking) }}', '{{ $booking->code }}', {{ $remaining }})">Bayar</button>
                      @endif
                      @if($booking->status === 'held')
                        <form method="POST" action="{{ route('bookings.confirm', $booking) }}" data-confirm="Konfirmasi booking {{ $booking->code }} tanpa pembayaran?">@csrf<button class="btn btn-sm">Konfirmasi</button></form>
                      @endif
                      @if($booking->status === 'confirmed')
                        <form method="POST" action="{{ route('bookings.checkin', $booking) }}">@csrf<button class="btn btn-sm">Check-in</button></form>
                      @elseif($booking->status === 'checked_in')
                        <form method="POST" action="{{ route('bookings.checkout', $booking) }}">@csrf<button class="btn btn-sm">Check-out</button></form>
                      @elseif($booking->status === 'held')
                        <form method="POST" action="{{ route('bookings.checkin', $booking) }}">@csrf<button class="btn btn-sm" disabled title="Hold belum dikonfirmasi">Check-in</button></form>
                      @endif
                      <a class="btn btn-sm btn-ghost" href="{{ route('bookings.proof', $booking) }}" target="_blank">Detail</a>
                      @if(in_array($booking->status, ['confirmed', 'held']))
                        <button class="btn btn-sm btn-danger" type="button" onclick="openCancel('{{ route('bookings.cancel', $booking) }}', '{{ $booking->code }}')">Batal</button>
                      @endif
                      @if($booking->status === 'confirmed')
                        <form method="POST" action="{{ route('bookings.noshow', $booking) }}" data-confirm="Tandai booking {{ $booking->code }} sebagai no-show?">@csrf<button class="btn btn-sm btn-ghost">No-show</button></form>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="9"><div class="empty"><span class="ic">📅</span>Tidak ada booking pada filter ini.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @endif

    @if($tab === 'recurring')
      <div class="card-b stack">
        <div class="alert info">
          <span class="ic">🔁</span>
          <div><b>Booking rutin dibuat otomatis</b> oleh scheduler harian untuk 14–30 hari ke depan, melewati tanggal yang slotnya sudah terisi.</div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Penyewa</th><th>Lapangan</th><th>Hari &amp; jam</th><th>Periode</th><th>Status</th><th></th></tr></thead>
            <tbody>
              @forelse($recurring as $item)
                <tr>
                  <td class="strong">{{ $item->customer_name }}<br><span class="muted-text">{{ $item->member?->code ?: 'Umum' }}</span></td>
                  <td>{{ $item->court?->name }}</td>
                  <td>Setiap {{ ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][$item->day_of_week] }} · {{ jam($item->start_time) }}–{{ jam($item->end_time) }}</td>
                  <td>{{ tanggal($item->valid_from) }} – {{ tanggal($item->valid_until) }}</td>
                  <td><span class="badge {{ $item->status === 'aktif' ? 'green' : 'grey' }}">{{ ucfirst($item->status) }}</span></td>
                  <td class="right">
                    @if($item->status === 'aktif')
                      <form method="POST" action="{{ route('bookings.recurring.stop', $item) }}" data-confirm="Hentikan jadwal rutin ini?">@csrf<button class="btn btn-sm btn-danger">Hentikan</button></form>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="6"><div class="empty"><span class="ic">🔁</span>Belum ada jadwal rutin.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="row justify-end">
          <button class="btn btn-primary" onclick="openModal('modalRecurring')">+ Jadwal Rutin Baru</button>
        </div>
      </div>
    @endif

    @if($tab === 'policy')
      <div class="card-b grid g2">
        <div class="stack">
          <h3>Kebijakan pembatalan</h3>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr><th>Waktu batal sebelum jadwal</th><th>Refund</th></tr></thead>
              <tbody>
                <tr><td>≥ 24 jam</td><td class="strong text-green">100% / pindah jadwal gratis</td></tr>
                <tr><td>6–24 jam</td><td class="strong text-amber">50%</td></tr>
                <tr><td>&lt; 6 jam</td><td class="strong text-red">0% (hangus)</td></tr>
                <tr><td>No-show</td><td class="strong text-red">0% + catatan pelanggaran</td></tr>
              </tbody>
            </table>
          </div>
          <div class="alert warn">
            <span class="ic">⚠️</span>
            <div><b>3× no-show dalam 30 hari</b> membuat member berstatus <i>perlu konfirmasi</i>; booking berikutnya membutuhkan DP.</div>
          </div>
        </div>
        <div class="stack">
          <h3>Batas &amp; aturan booking</h3>
          <div class="note-box">
            Durasi kelipatan 30 menit · Booking umum {{ setting('public_advance_days', 14) }} hari ke depan · Member {{ setting('member_advance_days', 30) }} hari · Hold {{ setting('hold_minutes', 15) }} menit · DP {{ setting('dp_percent', 50) }}%.
          </div>
          <div class="alert info">
            <span class="ic">ℹ️</span>
            <div>Perubahan kebijakan dilakukan di <b>Pengaturan</b> (Owner) atau halaman <b>Lapangan &amp; Tarif</b> (Admin). Semua perubahan tercatat di audit log.</div>
          </div>
          <div class="note-box">
            Kanal booking hanya telepon, WhatsApp, dan kunjungan langsung. Tidak ada halaman pemesanan publik.
          </div>
        </div>
      </div>
    @endif
  </div>

  {{-- Modal booking --}}
  <div class="modal-mask" id="modalBooking">
    <div class="modal wide">
      <form method="POST" action="{{ route('bookings.store') }}" id="bookingForm">
        @csrf
        <div class="modal-h">
          <div>
            <h3>Booking Baru</h3>
            <div class="sub">Slot dikunci server — double booking tidak mungkin terjadi.</div>
          </div>
          <button class="x" type="button" onclick="closeModal('modalBooking')">✕</button>
        </div>
        <div class="modal-b grid g-3-2">
          <div class="form-grid">
            <div class="field full">
              <label>Member (opsional)</label>
              <select class="select" name="member_id" id="b_member">
                <option value="">— Umum / non-member —</option>
                @foreach($members as $member)
                  <option value="{{ $member->id }}" data-name="{{ $member->name }}" data-phone="{{ $member->phone }}" data-discount="{{ $member->discountPct() }}">
                    {{ $member->name }} · {{ $member->code }} · diskon {{ (int) $member->discountPct() }}%
                  </option>
                @endforeach
              </select>
              <span class="hint">Member otomatis mendapat diskon sesuai paket aktif.</span>
            </div>
            <div class="field">
              <label>Nama penyewa <span class="req">*</span></label>
              <input class="input" name="customer_name" id="b_name" value="{{ old('customer_name') }}" required maxlength="120">
            </div>
            <div class="field">
              <label>No. HP / WhatsApp</label>
              <input class="input" name="customer_phone" id="b_phone" value="{{ old('customer_phone') }}" maxlength="30">
            </div>
            <div class="field">
              <label>Sumber booking</label>
              <select class="select" name="source">
                <option value="walkin">Datang langsung (walk-in)</option>
                <option value="telepon">Telepon</option>
                <option value="wa">WhatsApp</option>
              </select>
            </div>
            <div class="field">
              <label>Tanggal main <span class="req">*</span></label>
              <input class="input" type="date" name="booking_date" id="b_date" value="{{ $date->toDateString() }}" required>
            </div>
            <div class="field">
              <label>Lapangan <span class="req">*</span></label>
              <select class="select" name="court_id" id="b_court" required>
                @foreach($courts as $court)
                  <option value="{{ $court->id }}">{{ $court->name }} · {{ $court->type }}</option>
                @endforeach
              </select>
            </div>
            <div class="field">
              <label>Jam mulai <span class="req">*</span></label>
              <select class="select" name="start_time" id="b_start" required>
                @for($m = $pricing->toMinutes(setting('opening_time', '07:00')); $m < $pricing->toMinutes(setting('closing_time', '24:00')); $m += 30)
                  <option value="{{ $pricing->fromMinutes($m) }}">{{ $pricing->fromMinutes($m) }}</option>
                @endfor
              </select>
            </div>
            <div class="field">
              <label>Durasi</label>
              <select class="select" name="duration_minutes" id="b_duration">
                @for($d = 30; $d <= 480; $d += 30)
                  <option value="{{ $d }}" @selected($d === 60)>{{ $d < 60 ? $d . ' menit' : ($d / 60) . ' jam' }}</option>
                @endfor
              </select>
            </div>
            <div class="field full">
              <label>Catatan</label>
              <textarea class="input" name="notes" rows="2" maxlength="500" placeholder="Contoh: minta net diganti, butuh 2 kursi tambahan."></textarea>
            </div>
          </div>

          <div class="stack">
            <div class="card" style="box-shadow:none">
              <div class="card-h"><h3>Rincian harga</h3></div>
              <div class="card-b stack" id="b_rincian">
                <div class="alert info"><span class="ic">ℹ️</span><div>Pilih lapangan, tanggal, dan jam untuk melihat rincian tarif.</div></div>
              </div>
            </div>
            @if(auth()->user()->isOperator())
              <div class="card" style="box-shadow:none">
                <div class="card-h"><h3>Pembayaran</h3></div>
                <div class="card-b stack">
                  <div class="field">
                    <label>Total tagihan</label>
                    <input class="input" id="b_total" readonly placeholder="—">
                  </div>
                  <div class="alert info"><span class="ic">ℹ️</span><div>Pembayaran dicatat oleh kasir. Operator hanya membuat jadwal booking.</div></div>
                  <div class="note-box">Hold berlaku {{ setting('hold_minutes', 15) }} menit.</div>
                </div>
              </div>
            @else
              <div class="card" style="box-shadow:none">
                <div class="card-h"><h3>Pembayaran</h3></div>
                <div class="card-b stack">
                  <div class="field">
                    <label>Metode</label>
                    <select class="select" name="payment_method" id="b_payment_method">
                      <option value="cash">Tunai (kas shift)</option>
                      <option value="qris">QRIS</option>
                      <option value="transfer">Transfer</option>
                      <option value="debit">Debit / EDC</option>
                      <option value="deposit">Saldo deposit member</option>
                    </select>
                  </div>
                  <div class="field">
                    <label>Jumlah dibayar sekarang</label>
                    <div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" step="1" name="payment_amount" id="b_payment_amount" value="0"></div>
                  </div>
                  <input type="hidden" name="pay_at_venue" id="b_pay_at_venue" value="0">
                  <div class="row" style="gap:6px;flex-wrap:wrap">
                    <button class="btn btn-sm" type="button" id="b_dp_btn">DP {{ setting('dp_percent', 50) }}%</button>
                    <button class="btn btn-sm" type="button" id="b_full_btn">Lunas</button>
                    <button class="btn btn-sm" type="button" id="b_venue_btn">Bayar di tempat</button>
                  </div>
                  <div class="hint">Isi nominal, pilih <b>Bayar di tempat</b>, atau simpan sebagai hold. Konfirmasi dengan nominal 0 akan ditolak.</div>
                  <div class="field">
                    <label>Total tagihan</label>
                    <input class="input" id="b_total" readonly placeholder="—">
                  </div>
                  <div class="field">
                    <label>Referensi pembayaran (opsional)</label>
                    <input class="input" name="payment_reference" maxlength="80" placeholder="No. QRIS / transfer / EDC">
                  </div>
                  <div class="field">
                    <label>Masa hold</label>
                    <div class="note-box">Hold berlaku <span id="b_dp_hint">{{ setting('hold_minutes', 15) }}</span>. Pembayaran diabaikan untuk hold.</div>
                  </div>
                </div>
              </div>
            @endif
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalBooking')">Batal</button>
          <button class="btn" type="submit" name="hold" value="1">Simpan sebagai hold</button>
          <button class="btn btn-primary" type="submit" name="hold" value="0">Konfirmasi &amp; cetak bukti</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal pembayaran --}}
  <div class="modal-mask {{ $payBooking ? 'open' : '' }}" id="modalPayment">
    <div class="modal">
      <form method="POST" id="paymentForm" action="{{ $payBooking ? route('bookings.payment', $payBooking) : '' }}">
        @csrf
        <div class="modal-h">
          <div>
            <h3>Catat Pembayaran Booking</h3>
            <div class="sub"><span id="paymentCode">{{ $payBooking?->code }}</span> · sisa tagihan <span id="paymentRemaining">{{ $payBooking ? rupiah((float) $payBooking->total_amount - (float) $payBooking->paid_amount) : '—' }}</span></div>
          </div>
          <button class="x" type="button" onclick="closeModal('modalPayment')">✕</button>
        </div>
        <div class="modal-b stack">
          <div class="alert info"><span class="ic">ℹ️</span><div>Untuk booking berstatus <b>hold</b>, pembayaran yang disimpan akan otomatis mengonfirmasi jadwal.</div></div>
          <div class="form-grid">
            <div class="field">
              <label>Metode</label>
              <select class="select" name="method">
                <option value="cash">Tunai</option>
                <option value="qris">QRIS</option>
                <option value="transfer">Transfer</option>
                <option value="debit">Debit / EDC</option>
                <option value="deposit">Saldo deposit member</option>
              </select>
            </div>
            <div class="field">
              <label>Nominal</label>
              <div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="1" step="1" name="amount" id="paymentAmount" value="{{ $payBooking ? max((float) $payBooking->total_amount - (float) $payBooking->paid_amount, 0) : '' }}" required></div>
            </div>
            <div class="field full">
              <label>Referensi (opsional)</label>
              <input class="input" name="reference_no" maxlength="80">
            </div>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalPayment')">Batal</button>
          <button class="btn btn-primary">Simpan pembayaran</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal pembatalan --}}
  <div class="modal-mask {{ $cancelBooking ? 'open' : '' }}" id="modalCancel">
    <div class="modal">
      <form method="POST" id="cancelForm" action="{{ $cancelBooking ? route('bookings.cancel', $cancelBooking) : '' }}">
        @csrf
        <div class="modal-h">
          <div>
            <h3>Batalkan Booking</h3>
            <div class="sub"><span id="cancelCode">{{ $cancelBooking?->code }}</span> · refund mengikuti kebijakan waktu pembatalan.</div>
          </div>
          <button class="x" type="button" onclick="closeModal('modalCancel')">✕</button>
        </div>
        <div class="modal-b stack">
          <div class="alert warn">
            <span class="ic">⚠️</span>
            <div>Pembatalan tercatat di audit log. Refund tunai mengurangi kas shift yang aktif.</div>
          </div>
          <div class="field">
            <label>Alasan pembatalan <span class="req">*</span></label>
            <textarea class="input" name="reason" rows="3" required maxlength="500" placeholder="Contoh: pelanggan sakit, jadwal bentrok, permintaan pindah jadwal.">{{ old('reason') }}</textarea>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalCancel')">Batal</button>
          <button class="btn btn-danger">Batalkan booking</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal recurring --}}
  <div class="modal-mask" id="modalRecurring">
    <div class="modal wide">
      <form method="POST" action="{{ route('bookings.recurring.store') }}">
        @csrf
        <div class="modal-h">
          <div><h3>Jadwal Rutin Baru</h3><div class="sub">Dibuat otomatis oleh scheduler harian.</div></div>
          <button class="x" type="button" onclick="closeModal('modalRecurring')">✕</button>
        </div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field full">
              <label>Member (opsional)</label>
              <select class="select" name="member_id">
                <option value="">— Umum / komunitas —</option>
                @foreach($members as $member)
                  <option value="{{ $member->id }}">{{ $member->name }} · {{ $member->code }}</option>
                @endforeach
              </select>
            </div>
            <div class="field">
              <label>Nama penyewa <span class="req">*</span></label>
              <input class="input" name="customer_name" required maxlength="120">
            </div>
            <div class="field">
              <label>No. HP</label>
              <input class="input" name="customer_phone" maxlength="30">
            </div>
            <div class="field">
              <label>Lapangan <span class="req">*</span></label>
              <select class="select" name="court_id" required>
                @foreach($courts as $court)<option value="{{ $court->id }}">{{ $court->name }}</option>@endforeach
              </select>
            </div>
            <div class="field">
              <label>Hari</label>
              <select class="select" name="day_of_week" required>
                @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $index => $day)
                  <option value="{{ $index }}" @selected($index === 2)>{{ $day }}</option>
                @endforeach
              </select>
            </div>
            <div class="field">
              <label>Jam mulai</label>
              <input class="input" type="time" name="start_time" value="19:00" required>
            </div>
            <div class="field">
              <label>Jam selesai</label>
              <input class="input" type="time" name="end_time" value="21:00" required>
            </div>
            <div class="field">
              <label>Berlaku dari</label>
              <input class="input" type="date" name="valid_from" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="field">
              <label>Berlaku sampai</label>
              <input class="input" type="date" name="valid_until" value="{{ now()->addMonths(3)->toDateString() }}" required>
            </div>
            <div class="field full">
              <label>Catatan</label>
              <textarea class="input" name="notes" rows="2" maxlength="500"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalRecurring')">Batal</button>
          <button class="btn btn-primary">Simpan jadwal rutin</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  function openPayment(action, code, remaining) {
    const form = document.getElementById('paymentForm');
    form.action = action;
    document.getElementById('paymentCode').textContent = code;
    document.getElementById('paymentRemaining').textContent = rupiah(remaining);
    document.getElementById('paymentAmount').value = Math.round(remaining);
    openModal('modalPayment');
  }

  function openCancel(action, code) {
    const form = document.getElementById('cancelForm');
    form.action = action;
    document.getElementById('cancelCode').textContent = code;
    openModal('modalCancel');
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-copy]');
    if (btn) copyText(btn.dataset.copy, 'Pesan WhatsApp');
  });
</script>
@endpush
