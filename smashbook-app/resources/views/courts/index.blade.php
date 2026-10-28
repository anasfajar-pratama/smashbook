@extends('layouts.app')

@section('title', 'Master Lapangan & Tarif')
@section('crumb', 'Konfigurasi lapangan, kategori waktu, dan aturan harga')

@section('topbar-actions')
  @if(auth()->user()->isAdmin())
    <button class="btn btn-sm" type="button" onclick="openModal('modalCourt')">+ Lapangan</button>
  @endif
  @if(auth()->user()->isOwner())
    <button class="btn btn-primary btn-sm" type="button" onclick="openModal('modalTarif')">+ Aturan Tarif</button>
  @endif
@endsection

@section('content')
  <div class="grid g4">
    @foreach($courts as $court)
      @php
        $weekend = $court->priceRules->firstWhere('day_type', 'weekend');
        $prime = $court->priceRules->first(fn ($rule) => $rule->day_type === 'weekday' && $rule->label === 'Prime');
        $pct = $occupancy[$court->id] ?? 0;
      @endphp
      <div class="card">
        <div class="card-b stack">
          <div class="row between">
            <div class="row">
              <div class="icon-badge">🏸</div>
              <div>
                <div class="strong">{{ $court->name }}</div>
                <div class="muted-text">{{ $court->surface ?: $court->type }} · {{ $court->code }}</div>
              </div>
            </div>
            <span class="badge {{ $court->is_active ? 'green' : 'amber' }}">{{ $court->is_active ? 'Aktif' : 'Perawatan' }}</span>
          </div>
          <div class="divider"></div>
          <div class="row between"><span class="muted-text">Tarif weekend</span><span class="strong">{{ $weekend ? rupiah($weekend->price_per_hour) : '—' }}</span></div>
          <div class="row between"><span class="muted-text">Tarif prime</span><span class="strong">{{ $prime ? rupiah($prime->price_per_hour) : '—' }}</span></div>
          <div>
            <div class="row between"><span class="muted-text">Okupansi 7 hari</span><span class="strong">{{ $pct }}%</span></div>
            <div class="bar" style="margin-top:4px"><span class="{{ $pct < 50 ? 'amber' : '' }}" style="width:{{ $pct }}%"></span></div>
          </div>
          <div class="row" style="gap:6px">
            @if(auth()->user()->isAdmin())
              <button class="btn btn-sm" type="button"
                data-court-edit="{{ $court->id }}"
                data-name="{{ $court->name }}"
                data-type="{{ $court->type }}"
                data-surface="{{ $court->surface }}"
                data-capacity="{{ $court->capacity }}"
                data-sort="{{ $court->sort_order }}"
                onclick="openCourtEdit(this)">Ubah</button>
              <form method="POST" action="{{ route('courts.toggle', $court) }}">@csrf<button class="btn btn-sm">{{ $court->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-h">
      <div><h3>Aturan tarif (peak / off-peak)</h3><div class="sub">Harga per 30 menit dihitung dari tarif per jam. Booking yang melewati batas kategori dihitung per slot.</div></div>
      <div class="right"><span class="badge {{ $courts->first()?->priceRules->count() ? 'green' : 'amber' }}">{{ $courts->sum(fn ($c) => $c->priceRules->count()) }} aturan</span></div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Lapangan</th><th>Kategori</th><th>Hari</th><th>Jam</th><th class="num">Tarif/jam</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse($courts as $court)
            @foreach($court->priceRules->sortBy([['day_type', 'asc'], ['start_time', 'asc']]) as $rule)
              <tr>
                <td class="strong">{{ $court->name }}</td>
                <td>
                  <span class="badge {{ $rule->label === 'Prime' ? 'red' : ($rule->label === 'Weekend' ? 'blue' : ($rule->label === 'Night' ? 'violet' : 'grey')) }}">{{ $rule->label }}</span>
                </td>
                <td>{{ $rule->day_type === 'weekday' ? 'Senin–Jumat' : ($rule->day_type === 'weekend' ? 'Sabtu, Minggu, hari libur' : 'Semua hari') }}</td>
                <td class="nowrap">{{ jam($rule->start_time) }}–{{ jam($rule->end_time) }}</td>
                <td class="num strong">{{ rupiah($rule->price_per_hour) }}</td>
                <td><span class="badge green">Aktif</span></td>
                <td class="right">
                  @if(auth()->user()->isOwner())
                    <form method="POST" action="{{ route('courts.price-rules.destroy', $rule) }}" data-confirm="Hapus aturan tarif ini?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Hapus</button></form>
                  @endif
                </td>
              </tr>
            @endforeach
          @empty
            <tr><td colspan="7"><div class="empty"><span class="ic">🏸</span>Belum ada aturan tarif.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid g2">
    <div class="card">
      <div class="card-h">
        <div><h3>Hari libur nasional</h3><div class="sub">Tanggal pada daftar ini otomatis memakai kategori Weekend.</div></div>
        <div class="right">
          @if(auth()->user()->isAdmin())
            <button class="btn btn-sm" type="button" onclick="openModal('modalHoliday')">+ Tambah</button>
          @endif
        </div>
      </div>
      <div class="card-b tight">
        @forelse($holidays as $holiday)
          <div class="list-item">
            <div class="ic ico blue">📅</div>
            <div class="t"><b>{{ $holiday->name }}</b><small>{{ tanggal($holiday->date, 'l, j F Y') }}</small></div>
            <span class="badge {{ $holiday->is_peak ? 'blue' : 'grey' }}">{{ $holiday->is_peak ? 'Tarif weekend' : 'Normal' }}</span>
            @if(auth()->user()->isAdmin())
              <form method="POST" action="{{ route('courts.holidays.destroy', $holiday) }}" data-confirm="Hapus hari libur ini?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Hapus</button></form>
            @endif
          </div>
        @empty
          <div class="empty"><span class="ic">📅</span>Belum ada hari libur.</div>
        @endforelse
      </div>
    </div>

    <div class="card">
      <div class="card-h"><div><h3>Jam operasional &amp; kebijakan</h3><div class="sub">Berlaku untuk booking baru dan kalkulasi tarif.</div></div></div>
      <form method="POST" action="{{ route('courts.settings.update') }}" class="card-b">
        @csrf
        <div class="form-grid">
          <div class="field"><label>Jam buka</label><input class="input" name="opening_time" value="{{ old('opening_time', $settings['opening_time']) }}" required @disabled(! auth()->user()->isOwner())></div>
          <div class="field"><label>Jam tutup</label><input class="input" name="closing_time" value="{{ old('closing_time', $settings['closing_time']) }}" required @disabled(! auth()->user()->isOwner())><span class="hint">Gunakan 24:00 untuk tengah malam.</span></div>
          <div class="field"><label>Masa hold (menit)</label><input class="input" type="number" min="1" max="120" name="hold_minutes" value="{{ old('hold_minutes', $settings['hold_minutes']) }}" required @disabled(! auth()->user()->isOwner())></div>
          <div class="field"><label>DP default (%)</label><input class="input" type="number" min="0" max="100" name="dp_percent" value="{{ old('dp_percent', $settings['dp_percent']) }}" required @disabled(! auth()->user()->isOwner())></div>
          <div class="field"><label>Booking umum (hari)</label><input class="input" type="number" min="0" max="90" name="public_advance_days" value="{{ old('public_advance_days', $settings['public_advance_days']) }}" required @disabled(! auth()->user()->isOwner())></div>
          <div class="field"><label>Booking member (hari)</label><input class="input" type="number" min="0" max="365" name="member_advance_days" value="{{ old('member_advance_days', $settings['member_advance_days']) }}" required @disabled(! auth()->user()->isOwner())></div>
          <div class="field"><label>Batas diskon kasir (%)</label><input class="input" type="number" min="0" max="100" step="0.1" name="discount_limit_pct" value="{{ old('discount_limit_pct', $settings['discount_limit_pct']) }}" required @disabled(! auth()->user()->isOwner())></div>
          <div class="field"><label>Toleransi selisih kas</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" step="1000" name="cash_difference_tolerance" value="{{ old('cash_difference_tolerance', $settings['cash_difference_tolerance']) }}" required @disabled(! auth()->user()->isOwner())></div></div>
        </div>
        <div class="row justify-end mt-2">
          @if(auth()->user()->isOwner())
            <button class="btn btn-primary">Simpan pengaturan</button>
          @else
            <span class="muted-text">Pengaturan tarif global hanya dapat diubah Owner.</span>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-h"><h3>Catatan tarif &amp; promo</h3></div>
    <div class="card-b grid g3">
      <div class="note-box"><b>Diskon member</b><br>Diambil dari paket membership aktif dan hanya berlaku untuk sewa lapangan. Diskon tidak berlaku untuk produk POS.</div>
      <div class="note-box"><b>Tarif komunitas</b><br>Atur tarif khusus komunitas dengan menambahkan aturan tarif pada lapangan terkait, atau berikan diskon manual saat booking.</div>
      <div class="note-box"><b>Promo jam sepi</b><br>Belum ada tabel promo terpisah. Gunakan tarif off-peak yang lebih rendah pada jam sepi untuk efek yang sama.</div>
    </div>
  </div>

  <div class="modal-mask" id="modalTarif">
    <div class="modal">
      <form method="POST" action="{{ route('courts.price-rules.store') }}">
        @csrf
        <div class="modal-h"><div><h3>Aturan Tarif</h3><div class="sub">Tarif per jam; sistem membagi otomatis per 30 menit.</div></div><button class="x" type="button" onclick="closeModal('modalTarif')">✕</button></div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Lapangan</label><select class="select" name="court_id" required>@foreach($courts as $court)<option value="{{ $court->id }}">{{ $court->name }}</option>@endforeach</select></div>
            <div class="field"><label>Kategori</label><select class="select" name="day_type" required><option value="weekday">Hari kerja</option><option value="weekend">Weekend / hari libur</option><option value="all">Semua hari</option></select></div>
            <div class="field"><label>Jam mulai</label><input class="input" type="time" name="start_time" value="07:00" required></div>
            <div class="field"><label>Jam selesai</label><input class="input" type="time" name="end_time" value="16:00" required></div>
            <div class="field"><label>Tarif per jam</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" step="1000" name="price_per_hour" required></div></div>
            <div class="field"><label>Label</label><input class="input" name="label" required maxlength="60" placeholder="Off-Peak / Prime / Weekend"></div>
          </div>
        </div>
        <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalTarif')">Batal</button><button class="btn btn-primary">Simpan aturan</button></div>
      </form>
    </div>
  </div>

  <div class="modal-mask" id="modalCourt">
    <div class="modal">
      <form method="POST" action="{{ route('courts.store') }}">
        @csrf
        <div class="modal-h"><div><h3>Lapangan Baru</h3><div class="sub">Kode lapangan harus unik.</div></div><button class="x" type="button" onclick="closeModal('modalCourt')">✕</button></div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Kode</label><input class="input" name="code" required maxlength="20" placeholder="C5"></div>
            <div class="field"><label>Nama</label><input class="input" name="name" required maxlength="80" placeholder="Lapangan 5"></div>
            <div class="field"><label>Tipe</label><input class="input" name="type" value="reguler" required maxlength="40"></div>
            <div class="field"><label>Permukaan</label><input class="input" name="surface" maxlength="60" placeholder="Karpet Vinyl / Semen"></div>
            <div class="field"><label>Kapasitas</label><input class="input" type="number" name="capacity" value="4" min="1" max="20" required></div>
            <div class="field"><label>Urutan</label><input class="input" type="number" name="sort_order" value="{{ $courts->count() + 1 }}" min="0"></div>
          </div>
        </div>
        <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalCourt')">Batal</button><button class="btn btn-primary">Simpan lapangan</button></div>
      </form>
    </div>
  </div>

  <div class="modal-mask" id="modalCourtEdit">
    <div class="modal">
      <form method="POST" id="courtEditForm">
        @csrf @method('PUT')
        <div class="modal-h"><div><h3>Ubah Lapangan</h3><div class="sub">Perubahan tidak mengubah tarif yang sudah tersimpan di booking lama.</div></div><button class="x" type="button" onclick="closeModal('modalCourtEdit')">✕</button></div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Nama</label><input class="input" name="name" id="ce_name" required maxlength="80"></div>
            <div class="field"><label>Tipe</label><input class="input" name="type" id="ce_type" required maxlength="40"></div>
            <div class="field"><label>Permukaan</label><input class="input" name="surface" id="ce_surface" maxlength="60"></div>
            <div class="field"><label>Kapasitas</label><input class="input" type="number" name="capacity" id="ce_capacity" min="1" max="20" required></div>
            <div class="field"><label>Urutan</label><input class="input" type="number" name="sort_order" id="ce_sort" min="0"></div>
          </div>
        </div>
        <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalCourtEdit')">Batal</button><button class="btn btn-primary">Simpan perubahan</button></div>
      </form>
    </div>
  </div>

  <div class="modal-mask" id="modalHoliday">
    <div class="modal">
      <form method="POST" action="{{ route('courts.holidays.store') }}">
        @csrf
        <div class="modal-h"><div><h3>Hari Libur</h3><div class="sub">Tarif weekend otomatis berlaku pada tanggal ini.</div></div><button class="x" type="button" onclick="closeModal('modalHoliday')">✕</button></div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Tanggal</label><input class="input" type="date" name="date" required></div>
            <div class="field"><label>Nama</label><input class="input" name="name" required maxlength="80" placeholder="Natal / Tahun Baru"></div>
            <div class="field full"><label class="check-row"><input type="checkbox" name="is_peak" value="1" checked> Gunakan tarif weekend / peak</label></div>
          </div>
        </div>
        <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalHoliday')">Batal</button><button class="btn btn-primary">Simpan</button></div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  function openCourtEdit(button) {
    const id = button.dataset.courtEdit;
    const form = document.getElementById('courtEditForm');
    form.action = '{{ url('courts') }}/' + id;
    document.getElementById('ce_name').value = button.dataset.name;
    document.getElementById('ce_type').value = button.dataset.type;
    document.getElementById('ce_surface').value = button.dataset.surface || '';
    document.getElementById('ce_capacity').value = button.dataset.capacity;
    document.getElementById('ce_sort').value = button.dataset.sort;
    openModal('modalCourtEdit');
  }
</script>
@endpush
