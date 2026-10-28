@extends('layouts.app')

@section('title', 'Kelola Member')
@section('crumb', 'Data member, paket membership, dan saldo deposit')

@section('topbar-actions')
  <button class="btn btn-primary btn-sm" onclick="openModal('modalMember')">+ Member Baru</button>
@endsection

@section('content')
  @php
    $tab = request('tab', 'list');
    $tabLinks = ['list' => 'Daftar Member', 'packages' => 'Paket Membership', 'noshow' => 'No-show & Sanksi'];
    $statusMeta = function ($member) {
        return match ($member->effectiveStatus()) {
            'akan_expired' => ['Akan expired', 'amber'],
            'expired' => ['Expired', 'grey'],
            'perlu_konfirmasi' => ['Perlu konfirmasi', 'red'],
            'suspend' => ['Suspend', 'red'],
            'blacklist' => ['Blacklist', 'dark'],
            default => ['Aktif', 'green'],
        };
    };
  @endphp

  <div class="grid g4">
    <div class="card kpi"><div class="row between"><span class="lbl">Member aktif</span><span class="ico brand">👥</span></div><div class="val">{{ $stats['active'] }}</div><div class="dlt up">Member dengan paket berjalan</div></div>
    <div class="card kpi"><div class="row between"><span class="lbl">Akan kedaluwarsa</span><span class="ico amber">⏳</span></div><div class="val">{{ $stats['expiring'] }}</div><div class="dlt neutral">Dalam 7 hari ke depan</div></div>
    <div class="card kpi"><div class="row between"><span class="lbl">Sudah kedaluwarsa</span><span class="ico red">📅</span></div><div class="val">{{ $stats['expired'] }}</div><div class="dlt neutral">Perlu follow-up perpanjangan</div></div>
    <div class="card kpi"><div class="row between"><span class="lbl">Total saldo deposit</span><span class="ico blue">💰</span></div><div class="val">{{ rupiah($stats['deposit']) }}</div><div class="dlt neutral">Kewajiban saldo member</div></div>
  </div>

  <div class="card">
    <div class="tabs-bar">
      @foreach($tabLinks as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }}</a>
      @endforeach
    </div>

    @if($tab === 'list')
      <div class="card-b">
        <form method="GET" action="{{ route('members.index') }}" class="filter-bar">
          <input type="hidden" name="tab" value="list">
          <div class="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input class="input" name="q" value="{{ $search }}" placeholder="Cari nama, kode member, atau nomor HP...">
          </div>
          <div class="field" style="min-width:160px">
            <select class="select" name="status">
              <option value="">Semua status</option>
              <option value="aktif" @selected($status === 'aktif')>Aktif</option>
              <option value="akan_expired" @selected($status === 'akan_expired')>Akan expired</option>
              <option value="expired" @selected($status === 'expired')>Expired</option>
              <option value="perlu_konfirmasi" @selected($status === 'perlu_konfirmasi')>Perlu konfirmasi</option>
            </select>
          </div>
          <button class="btn">Terapkan</button>
          <div class="spacer"></div>
          <span class="muted-text">{{ $members->total() }} member</span>
        </form>
      </div>

      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Member</th><th>Kontak</th><th>Paket</th><th>Masa aktif</th><th class="num">Deposit</th><th class="num">Booking</th><th class="num">Belanja POS</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @forelse($members as $member)
              @php
                [$label, $badge] = $statusMeta($member);
                $sub = $member->activeSubscription;
              @endphp
              <tr>
                <td>
                  <div class="member-cell">
                    <div class="avatar-sm green">{{ mb_substr($member->name, 0, 1) }}{{ mb_substr(explode(' ', $member->name)[1] ?? '', 0, 1) }}</div>
                    <div>
                      <div class="strong">{{ $member->name }}</div>
                      <div class="muted-text mono">{{ $member->code }}</div>
                    </div>
                  </div>
                </td>
                <td>{{ $member->phone }}<br><span class="muted-text">{{ $member->email ?: '—' }}</span></td>
                <td>
                  @if($member->activeSubscription?->membershipType ?? $member->membershipType)
                    <span class="badge blue">{{ ($member->activeSubscription?->membershipType ?? $member->membershipType)->name }}</span>
                  @else
                    <span class="badge grey">Tanpa paket</span>
                  @endif
                </td>
                <td>
                  @if($sub)
                    {{ tanggal($sub->start_date) }} – {{ tanggal($sub->end_date) }}<br>
                    <span class="muted-text">{{ (int) now()->diffInDays($sub->end_date, false) >= 0 ? 'sisa ' . (int) now()->diffInDays($sub->end_date, false) . ' hari' : 'habis ' . abs((int) now()->diffInDays($sub->end_date, false)) . ' hari lalu' }}</span>
                  @else
                    <span class="muted-text">—</span>
                  @endif
                </td>
                <td class="num">{{ rupiah($member->deposit_balance) }}</td>
                <td class="num">{{ $member->bookings_count }}×</td>
                <td class="num">{{ rupiah($member->pos_total ?? 0) }}</td>
                <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                <td>
                  <div class="tbl-actions">
                    <a class="btn btn-sm" href="{{ route('members.index', ['tab' => 'list', 'q' => $search, 'status' => $status, 'detail' => $member->id]) }}">Detail</a>
                    @if(auth()->user()->isCashier())
                      <button class="btn btn-sm" type="button" onclick="openRenew('{{ route('members.renew', $member) }}', '{{ addslashes($member->name) }}')">Perpanjang</button>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="9"><div class="empty"><span class="ic">👥</span>Tidak ada member pada filter ini.</div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{ $members->links('partials.pagination') }}
    @endif

    @if($tab === 'packages')
      <div class="card-b grid g4">
        @foreach($types as $type)
          <div class="card" style="box-shadow:none">
            <div class="card-b stack">
              <div class="row between">
                <span class="badge {{ $loop->index === 2 ? 'dark' : ($loop->index === 3 ? 'violet' : 'blue') }}">{{ $loop->index === 3 ? 'Komunitas' : 'Paket' }}</span>
                <span class="badge green">{{ $type->members_count }} member</span>
              </div>
              <h3>{{ $type->name }}</h3>
              <div style="font-size:22px;font-weight:800">{{ rupiah($type->price) }}<span class="muted-text" style="font-size:12px;font-weight:500"> / {{ $type->duration_days }} hari</span></div>
              <div class="divider"></div>
              <div class="stack" style="gap:6px;font-size:12.5px">
                <div>✓ Diskon sewa {{ (int) $type->discount_pct }}%</div>
                <div>✓ Booking di muka {{ setting('member_advance_days', 30) }} hari</div>
                @if($type->free_hours_per_month > 0)<div>✓ {{ $type->free_hours_per_month }} jam gratis/bulan</div>@endif
                @if($type->priority_booking)<div>✓ Prioritas jam ramai</div>@endif
                @foreach(array_filter(array_map('trim', explode("\n", (string) $type->benefits))) as $benefit)
                  <div>✓ {{ $benefit }}</div>
                @endforeach
              </div>
              <div class="note-box" style="margin-top:6px">Perpanjangan menambahkan durasi dari tanggal berakhir sebelumnya, bukan dari hari ini.</div>
            </div>
          </div>
        @endforeach
      </div>
    @endif

    @if($tab === 'noshow')
      <div class="card-b stack">
        <div class="alert warn">
          <span class="ic">⚠️</span>
          <div><b>Aturan sanksi:</b> 3× no-show dalam 30 hari membuat member berstatus <i>perlu konfirmasi</i>. Booking berikutnya membutuhkan DP.</div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Member</th><th class="num">No-show 30 hari</th><th class="num">Total no-show</th><th>Status</th><th></th></tr></thead>
            <tbody>
              @forelse($noShowMembers as $member)
                @php [$label, $badge] = $statusMeta($member); @endphp
                <tr>
                  <td><div class="strong">{{ $member->name }}</div><div class="muted-text mono">{{ $member->code }} · {{ $member->phone }}</div></td>
                  <td class="num strong {{ $member->recent_noshow >= 3 ? 'text-red' : 'text-amber' }}">{{ $member->recent_noshow }}×</td>
                  <td class="num">{{ $member->no_show_count }}×</td>
                  <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                  <td class="right">
                    @if(auth()->user()->isCashier())
                      <form method="POST" action="{{ route('members.reset-noshow', $member) }}" data-confirm="Reset sanksi no-show member ini?">@csrf<button class="btn btn-sm">Reset sanksi</button></form>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="5"><div class="empty"><span class="ic">✅</span>Tidak ada member bermasalah.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>

  {{-- Modal member baru --}}
  <div class="modal-mask" id="modalMember">
    <div class="modal wide">
      <form method="POST" action="{{ route('members.store') }}">
        @csrf
        <div class="modal-h">
          <div><h3>Member Baru</h3><div class="sub">Kode member dibuat otomatis (MBR-xxxxx).</div></div>
          <button class="x" type="button" onclick="closeModal('modalMember')">✕</button>
        </div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Nama lengkap <span class="req">*</span></label><input class="input" name="name" required maxlength="120"></div>
            <div class="field"><label>Nomor HP / WhatsApp <span class="req">*</span></label><input class="input" name="phone" required maxlength="30"><span class="hint">Satu nomor = satu member aktif.</span></div>
            <div class="field"><label>Email</label><input class="input" type="email" name="email" maxlength="120"></div>
            <div class="field"><label>Tanggal lahir</label><input class="input" type="date" name="birth_date"></div>
            <div class="field"><label>Jenis kelamin</label><select class="select" name="gender"><option value="">—</option><option>Laki-laki</option><option>Perempuan</option></select></div>
            <div class="field"><label>Tanggal mulai</label><input class="input" type="date" name="start_date" value="{{ now()->toDateString() }}" required></div>
            <div class="field"><label>Paket membership</label>
              <select class="select" name="member_type_id">
                <option value="">Tanpa paket (member dasar)</option>
                @foreach($types as $type)
                  <option value="{{ $type->id }}">{{ $type->name }} — {{ rupiah($type->price) }} / {{ $type->duration_days }} hari</option>
                @endforeach
              </select>
            </div>
            <div class="field"><label>Cara bayar paket</label>
              <select class="select" name="payment_method">
                <option value="cash">Tunai (kas shift)</option>
                <option value="qris">QRIS</option>
                <option value="transfer">Transfer</option>
                <option value="debit">Debit / EDC</option>
              </select>
            </div>
            <div class="field"><label>Deposit awal</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" step="1000" name="initial_deposit" value="0"></div></div>
            <div class="field"><label>Cara bayar deposit</label>
              <select class="select" name="deposit_method">
                <option value="cash">Tunai (kas shift)</option>
                <option value="qris">QRIS</option>
                <option value="transfer">Transfer</option>
                <option value="debit">Debit / EDC</option>
              </select>
            </div>
            <div class="field full"><label>Alamat</label><textarea class="input" name="address" rows="2" maxlength="500"></textarea></div>
            <div class="field full"><label>Catatan</label><textarea class="input" name="notes" rows="2" maxlength="500"></textarea></div>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalMember')">Batal</button>
          <button class="btn btn-primary">Simpan member</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal renew --}}
  <div class="modal-mask" id="modalRenew">
    <div class="modal">
      <form method="POST" id="renewForm">
        @csrf
        <div class="modal-h">
          <div><h3>Perpanjang Membership</h3><div class="sub" id="renewName"></div></div>
          <button class="x" type="button" onclick="closeModal('modalRenew')">✕</button>
        </div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field full"><label>Paket</label>
              <select class="select" name="membership_type_id" required>
                @foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }} — {{ rupiah($type->price) }} / {{ $type->duration_days }} hari</option>@endforeach
              </select>
            </div>
            <div class="field"><label>Mulai dari (opsional)</label><input class="input" type="date" name="start_date"><span class="hint">Kosongkan untuk melanjutkan dari tanggal berakhir.</span></div>
            <div class="field"><label>Cara bayar</label>
              <select class="select" name="payment_method" required>
                <option value="cash">Tunai</option><option value="qris">QRIS</option><option value="transfer">Transfer</option><option value="debit">Debit / EDC</option>
              </select>
            </div>
            <div class="field full"><label>Referensi</label><input class="input" name="reference_no" maxlength="80"></div>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalRenew')">Batal</button>
          <button class="btn btn-primary">Perpanjang</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal topup --}}
  <div class="modal-mask" id="modalTopup">
    <div class="modal">
      <form method="POST" id="topupForm">
        @csrf
        <div class="modal-h">
          <div><h3>Top-up Deposit</h3><div class="sub" id="topupName"></div></div>
          <button class="x" type="button" onclick="closeModal('modalTopup')">✕</button>
        </div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Nominal</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="1" step="1000" name="amount" required></div></div>
            <div class="field"><label>Metode</label><select class="select" name="method" required><option value="cash">Tunai</option><option value="qris">QRIS</option><option value="transfer">Transfer</option><option value="debit">Debit / EDC</option></select></div>
            <div class="field full"><label>Referensi</label><input class="input" name="reference_no" maxlength="80"></div>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalTopup')">Batal</button>
          <button class="btn btn-primary">Simpan top-up</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal detail --}}
  @if($detail)
    <div class="modal-mask open" id="modalDetail">
      <div class="modal wide">
        <div class="modal-h">
          <div class="member-head">
            <div class="avatar-lg">{{ mb_substr($detail->name, 0, 1) }}{{ mb_substr(explode(' ', $detail->name)[1] ?? '', 0, 1) }}</div>
            <div>
              <h3>{{ $detail->name }}</h3>
              <div class="sub">{{ $detail->code }} · {{ $detail->activeSubscription?->membershipType?->name ?? $detail->membershipType?->name ?? 'Tanpa paket' }} · bergabung {{ tanggal($detail->join_date) }}</div>
            </div>
          </div>
          <button class="x" type="button" onclick="closeModal('modalDetail')">✕</button>
        </div>
        <div class="tabs" data-tabs="detail">
          <button class="on" type="button" data-tab="profil">Profil</button>
          <button type="button" data-tab="booking">Riwayat Booking</button>
          <button type="button" data-tab="pos">Transaksi POS</button>
          <button type="button" data-tab="deposit">Deposit</button>
        </div>
        <div class="modal-b">
          <div data-panel="detail/profil">
            <div class="grid g3">
              <div class="card kpi" style="box-shadow:none"><span class="lbl">Total booking</span><div class="val" style="font-size:19px">{{ $detail->bookings->count() }}×</div></div>
              <div class="card kpi" style="box-shadow:none"><span class="lbl">Belanja POS</span><div class="val" style="font-size:19px">{{ rupiah($detail->posSales->where('status', 'paid')->sum('total_amount')) }}</div></div>
              <div class="card kpi" style="box-shadow:none"><span class="lbl">Saldo deposit</span><div class="val text-green" style="font-size:19px">{{ rupiah($detail->deposit_balance) }}</div></div>
            </div>
            <div class="grid g2" style="margin-top:12px;gap:12px">
              <div><div class="muted-text">Nomor HP</div><div class="strong">{{ $detail->phone }}</div></div>
              <div><div class="muted-text">Email</div><div class="strong">{{ $detail->email ?: '—' }}</div></div>
              <div><div class="muted-text">Masa aktif</div><div class="strong">{{ $detail->activeSubscription ? tanggal($detail->activeSubscription->start_date) . ' – ' . tanggal($detail->activeSubscription->end_date) : '—' }}</div></div>
              <div><div class="muted-text">No-show 30 hari</div><div class="strong">{{ $detail->bookings->where('status', 'no_show')->where('booking_date', '>=', now()->subDays(30))->count() }}×</div></div>
              <div><div class="muted-text">Alamat</div><div class="strong">{{ $detail->address ?: '—' }}</div></div>
              <div><div class="muted-text">Status</div><div class="strong">{{ ucfirst(str_replace('_', ' ', $detail->effectiveStatus())) }}</div></div>
            </div>
            @if($detail->notes)<div class="note-box" style="margin-top:12px">{{ $detail->notes }}</div>@endif
          </div>

          <div data-panel="detail/booking" style="display:none">
            <div class="table-wrap">
              <table class="tbl">
                <thead><tr><th>Kode</th><th>Jadwal</th><th>Lapangan</th><th class="num">Total</th><th>Status</th></tr></thead>
                <tbody>
                  @forelse($detail->bookings as $booking)
                    <tr><td class="mono">{{ $booking->code }}</td><td>{{ tanggal($booking->booking_date) }} · {{ jam($booking->start_time) }}–{{ jam($booking->end_time) }}</td><td>{{ $booking->court?->name }}</td><td class="num">{{ rupiah($booking->total_amount) }}</td><td><span class="badge {{ $booking->status === 'completed' ? 'green' : ($booking->status === 'checked_in' ? 'blue' : ($booking->status === 'cancelled' ? 'grey' : 'amber')) }}">{{ str_replace('_', ' ', ucfirst($booking->status)) }}</span></td></tr>
                  @empty
                    <tr><td colspan="5"><div class="empty">Belum ada booking.</div></td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

          <div data-panel="detail/pos" style="display:none">
            <div class="table-wrap">
              <table class="tbl">
                <thead><tr><th>Transaksi</th><th>Waktu</th><th class="num">Total</th><th>Status</th></tr></thead>
                <tbody>
                  @forelse($detail->posSales as $sale)
                    <tr><td class="mono">{{ $sale->code }}</td><td>{{ tanggal($sale->created_at, 'j M Y H:i') }}</td><td class="num">{{ rupiah($sale->total_amount) }}</td><td><span class="badge {{ $sale->status === 'paid' ? 'green' : 'red' }}">{{ ucfirst($sale->status) }}</span></td></tr>
                  @empty
                    <tr><td colspan="4"><div class="empty">Belum ada transaksi POS.</div></td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

          <div data-panel="detail/deposit" style="display:none">
            <div class="row between" style="margin-bottom:10px">
              <div><div class="muted-text">Saldo saat ini</div><div style="font-size:22px;font-weight:800;color:#047857">{{ rupiah($detail->deposit_balance) }}</div></div>
              <button class="btn btn-sm" type="button" onclick="openTopup('{{ route('members.topup', $detail) }}', '{{ addslashes($detail->name) }}')">+ Top-up</button>
            </div>
            <div class="table-wrap">
              <table class="tbl">
                <thead><tr><th>Waktu</th><th>Jenis</th><th>Referensi</th><th class="num">Nominal</th><th class="num">Saldo setelah</th></tr></thead>
                <tbody>
                  @forelse($detail->deposits as $deposit)
                    <tr>
                      <td>{{ tanggal($deposit->created_at, 'j M Y H:i') }}</td>
                      <td><span class="badge {{ $deposit->type === 'topup' ? 'green' : ($deposit->type === 'usage' ? 'red' : 'grey') }}">{{ ucfirst($deposit->type) }}</span></td>
                      <td class="mono">{{ $deposit->ref_type ? strtoupper($deposit->ref_type) . ' #' . $deposit->ref_id : '—' }}</td>
                      <td class="num {{ (float) $deposit->amount < 0 ? 'text-red' : 'text-green' }}">{{ rupiah($deposit->amount) }}</td>
                      <td class="num">{{ rupiah($deposit->balance_after) }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="5"><div class="empty">Belum ada mutasi deposit.</div></td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalDetail')">Tutup</button>
          <button class="btn" type="button" onclick="openTopup('{{ route('members.topup', $detail) }}', '{{ addslashes($detail->name) }}')">Top-up Deposit</button>
          <button class="btn btn-primary" type="button" onclick="openRenew('{{ route('members.renew', $detail) }}', '{{ addslashes($detail->name) }}')">Perpanjang Membership</button>
        </div>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
<script>
  function openRenew(action, name) {
    document.getElementById('renewForm').action = action;
    document.getElementById('renewName').textContent = name;
    openModal('modalRenew');
  }
  function openTopup(action, name) {
    document.getElementById('topupForm').action = action;
    document.getElementById('topupName').textContent = name;
    openModal('modalTopup');
  }

  document.addEventListener('click', (e) => {
    const tab = e.target.closest('[data-tab]');
    if (!tab) return;
    const group = tab.closest('[data-tabs]')?.dataset.tabs;
    if (!group) return;
    const name = tab.dataset.tab;
    document.querySelectorAll('[data-panel^="' + group + '/"]').forEach((panel) => {
      panel.style.display = panel.dataset.panel === group + '/' + name ? '' : 'none';
    });
    tab.parentElement.querySelectorAll('[data-tab]').forEach((button) => button.classList.toggle('on', button === tab));
  });
</script>
@endpush
