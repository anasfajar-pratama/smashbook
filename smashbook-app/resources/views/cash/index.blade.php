@extends('layouts.app')

@section('title', 'Kas Harian')
@section('crumb', 'Sesi kas per shift · rekonsiliasi & selisih')

@section('topbar-actions')
  @if($active)
    <button class="btn btn-sm" type="button" onclick="openModal('modalMovement')">+ Mutasi kas</button>
    <button class="btn btn-danger btn-sm" type="button" onclick="document.getElementById('closeForm').scrollIntoView({behavior:'smooth'})">Tutup shift</button>
  @endif
@endsection

@section('content')
  @php
    $tabLinks = [
      'aktif' => 'Sesi Aktif',
      'rekap' => 'Rekap Harian',
      'riwayat' => 'Riwayat Sesi',
      'setoran' => 'Setoran Bank',
    ];
    if (auth()->user()->isOperator()) {
      unset($tabLinks['setoran']);
    }
    $totalIn = $active ? (float) $ledger->where('type', 'in')->where('category', '!=', 'modal_awal')->sum('amount') : 0;
    $totalOut = $active ? (float) $ledger->where('type', 'out')->sum('amount') : 0;
    $nonCash = $methodTotals ? $methodTotals['qris'] + $methodTotals['transfer'] + $methodTotals['debit'] : 0;
    $allPayments = $methodTotals ? array_sum($methodTotals) : 0;
  @endphp

  <div class="card">
    <div class="tabs-bar">
      @foreach($tabLinks as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }}</a>
      @endforeach
    </div>

    @if($tab === 'aktif')
      <div class="card-b stack">
        @if(! $active)
          <div class="alert info">
            <span class="ic">ℹ️</span>
            <div><b>Belum ada sesi kas terbuka.</b> Buka shift terlebih dahulu agar pembayaran tunai booking dan POS dapat dicatat.</div>
          </div>
          <div class="card" style="box-shadow:none;max-width:620px">
            <div class="card-h"><h3>Buka Shift</h3></div>
            <form method="POST" action="{{ route('cash.open') }}" class="card-b">
              @csrf
              <div class="form-grid">
                <div class="field">
                  <label>Shift</label>
                  <select class="select" name="shift" required>
                    <option value="pagi" @selected(now()->hour < 15)>Pagi (07:00–15:00)</option>
                    <option value="sore" @selected(now()->hour >= 15 && now()->hour < 21)>Sore (15:00–21:00)</option>
                    <option value="malam" @selected(now()->hour >= 21)>Malam (21:00–24:00)</option>
                  </select>
                </div>
                <div class="field">
                  <label>Modal awal (Rp)</label>
                  <div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" step="1000" name="opening_balance" value="300000" required></div>
                </div>
              </div>
              <div class="row justify-end mt-2">
                <button class="btn btn-primary">Buka sesi kas</button>
              </div>
            </form>
          </div>
        @else
          <div class="card" style="box-shadow:none">
            <div class="card-h">
              <div>
                <h3>Sesi kas aktif — {{ $active->code }}</h3>
                <div class="sub">Dibuka oleh {{ $active->cashier?->name }} · {{ $active->opened_at->format('j M Y H:i') }} · belum ditutup</div>
              </div>
              <div class="right"><span class="badge green">OPEN</span></div>
            </div>
            <div class="card-b">
              <div class="recon">
                <div><div class="l">Modal awal</div><div class="v">{{ rupiah($active->opening_balance) }}</div></div>
                <div><div class="l">Tunai masuk</div><div class="v text-green">+ {{ rupiah($totalIn) }}</div></div>
                <div><div class="l">Tunai keluar</div><div class="v text-red">− {{ rupiah($totalOut) }}</div></div>
              </div>
            </div>
          </div>

          <div class="grid g2">
            <div class="stack">
              <div class="money-row">
                <div class="ic ico brand">💵</div>
                <div class="t"><b>Saldo tunai seharusnya</b><small>Modal awal + tunai masuk − tunai keluar</small></div>
                <div class="v" id="expectedVal" data-val="{{ $expected }}" data-tolerance="{{ setting('cash_difference_tolerance', 10000) }}">{{ rupiah($expected) }}</div>
              </div>
              <div class="money-row">
                <div class="ic ico blue">📲</div>
                <div class="t"><b>Non-tunai (QRIS + Transfer + Debit)</b><small>Tidak masuk kas fisik, tetap dicatat untuk rekonsiliasi</small></div>
                <div class="v">{{ rupiah($nonCash) }}</div>
              </div>
              <div class="money-row">
                <div class="ic ico violet">🧾</div>
                <div class="t"><b>Total pembayaran shift</b><small>Semua metode yang tercatat atas nama kasir ini</small></div>
                <div class="v">{{ rupiah($allPayments) }}</div>
              </div>

              <div class="card" style="box-shadow:none;background:#f8fafc">
                <div class="card-h"><h3 id="closeForm">Hitung fisik kas &amp; tutup shift</h3></div>
                <form method="POST" action="{{ route('cash.close') }}" class="card-b stack">
                  @csrf
                  <div class="field">
                    <label>Uang fisik dihitung kasir</label>
                    <div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="0" step="1000" name="physical_balance" id="fisikInput" value="{{ old('physical_balance') }}" required></div>
                    <span class="hint">Hitung seluruh pecahan di kotak kas, lalu masukkan totalnya.</span>
                  </div>
                  <div class="row" style="gap:6px">
                    <button class="btn btn-sm" type="button" data-simulate="{{ $expected }}">Simulasi: sesuai</button>
                    <button class="btn btn-sm" type="button" data-simulate="{{ max($expected - 7000, 0) }}">Simulasi: kurang 7rb</button>
                  </div>
                  <div id="selisihBox" style="display:none;background:#fff;border:1px solid var(--line);border-radius:10px;padding:12px">
                    <div class="row between"><span class="muted-text">Selisih</span><span class="v" style="font-size:20px;font-weight:800"></span></div>
                    <div class="ket muted-text" style="margin-top:3px"></div>
                  </div>
                  <div class="field">
                    <label>Catatan shift (wajib bila selisih besar)</label>
                    <textarea class="input" name="notes" rows="2" maxlength="500" placeholder="Contoh: kurang receh, sudah dicocokkan ulang."></textarea>
                  </div>
                  <div class="field" id="ownerPinWrap" style="display:none">
                    <label>PIN Owner (verifikasi selisih)</label>
                    <input class="input" type="password" name="owner_pin" maxlength="6" placeholder="PIN Owner">
                  </div>
                  <button class="btn btn-danger btn-block" data-confirm="Tutup shift sekarang? Pastikan uang fisik sudah dihitung.">Tutup Shift</button>
                </form>
              </div>
            </div>

            <div class="stack">
              <div class="card">
                <div class="card-h"><h3>Kas keluar / petty cash</h3><div class="right"><button class="btn btn-sm" type="button" onclick="openModal('modalMovement')">+ Catat</button></div></div>
                <div class="card-b tight">
                  @forelse($pettyCash as $movement)
                    <div class="list-item">
                      <div class="ic ico amber">{{ $movement->category === 'pembelian' ? '🏸' : '🧾' }}</div>
                      <div class="t"><b>{{ $movement->description }}</b><small>{{ $movement->created_at->format('H:i') }} · {{ $movement->attachment ? 'nota terlampir' : 'tanpa nota' }}</small></div>
                      <div class="v out">− {{ rupiah($movement->amount) }}</div>
                    </div>
                  @empty
                    <div class="empty"><span class="ic">🧾</span>Belum ada kas keluar.</div>
                  @endforelse
                </div>
              </div>

              <div class="card">
                <div class="card-h"><h3>Ringkasan metode bayar</h3></div>
                <div class="card-b stack">
                  @php
                    $methods = $methodTotals ?? ['cash' => 0, 'qris' => 0, 'transfer' => 0, 'debit' => 0, 'deposit' => 0];
                    $paymentTotal = max(array_sum($methods), 1);
                  @endphp
                  @foreach(['cash' => ['Tunai', 'brand'], 'qris' => ['QRIS', 'blue'], 'transfer' => ['Transfer', 'violet'], 'debit' => ['Debit / EDC', 'amber'], 'deposit' => ['Deposit member', 'cyan']] as $key => $meta)
                    @php $pct = round(($methods[$key] ?? 0) / $paymentTotal * 100); @endphp
                    <div>
                      <div class="row between"><span class="muted-text">{{ $meta[0] }}</span><span class="strong">{{ rupiah($methods[$key] ?? 0) }} · {{ $pct }}%</span></div>
                      <div class="bar" style="margin-top:4px"><span class="{{ $meta[1] === 'brand' ? '' : $meta[1] }}" style="width:{{ $pct }}%"></span></div>
                    </div>
                  @endforeach
                  <div class="divider"></div>
                  <div class="row between"><span class="muted-text">Total semua metode</span><span class="strong">{{ rupiah(array_sum($methods)) }}</span></div>
                </div>
              </div>
            </div>
          </div>

          <div class="card" style="box-shadow:none">
            <div class="card-h">
              <div><h3>Mutasi kas sesi ini</h3><div class="sub">Urut waktu · saldo berjalan</div></div>
              <div class="right"><button class="btn btn-sm" type="button" onclick="openModal('modalMovement')">+ Mutasi</button></div>
            </div>
            <div class="table-wrap">
              <table class="tbl">
                <thead><tr><th>Waktu</th><th>Keterangan</th><th>Kategori</th><th>Referensi</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo</th></tr></thead>
                <tbody>
                  @php $running = 0; @endphp
                  @forelse($ledger as $movement)
                    @php
                      $running += $movement->type === 'in' ? (float) $movement->amount : -(float) $movement->amount;
                      $catBadge = match($movement->category) {
                        'modal_awal' => 'grey',
                        'booking' => 'blue',
                        'pos' => 'violet',
                        'deposit' => 'green',
                        'kas_kecil' => 'amber',
                        'pembelian' => 'amber',
                        'setoran_bank' => 'blue',
                        default => 'grey',
                      };
                    @endphp
                    <tr>
                      <td class="nowrap">{{ $movement->created_at->format('H:i') }}</td>
                      <td>{{ $movement->description }}</td>
                      <td><span class="badge {{ $catBadge }}">{{ str_replace('_', ' ', ucfirst($movement->category)) }}</span></td>
                      <td class="mono">{{ $movement->ref_type ? strtoupper($movement->ref_type) : '—' }}{{ $movement->ref_id ? ' #' . $movement->ref_id : '' }}</td>
                      <td class="num v in">{{ $movement->type === 'in' ? rupiah($movement->amount) : '' }}</td>
                      <td class="num v out">{{ $movement->type === 'out' ? rupiah($movement->amount) : '' }}</td>
                      <td class="num strong">{{ rupiah($running) }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="7"><div class="empty"><span class="ic">💰</span>Belum ada mutasi kas.</div></td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            <div class="tfoot">
              <span class="lbl">Saldo tunai seharusnya</span>
              <span class="grand">{{ rupiah($expected) }}</span>
            </div>
          </div>
        @endif
      </div>
    @endif

    @if($tab === 'rekap')
      <div class="card-b stack">
        <div class="row between">
          <div><h3>Rekap Kas Harian</h3><div class="muted-text">{{ $recap['date']->locale('id')->translatedFormat('l, j F Y') }}</div></div>
          <div class="badge dark">{{ $recap['sessions']->count() }} sesi</div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Shift</th><th>Petugas</th><th>Jam</th><th class="num">Modal awal</th><th class="num">Tunai masuk</th><th class="num">Tunai keluar</th><th class="num">Seharusnya</th><th class="num">Fisik</th><th class="num">Selisih</th><th>Status</th></tr></thead>
            <tbody>
              @forelse($recap['sessions'] as $session)
                @php
                  $sIn = (float) $session->movements()->where('type', 'in')->where('category', '!=', 'modal_awal')->sum('amount');
                  $sOut = (float) $session->movements()->where('type', 'out')->sum('amount');
                  $sExpected = $session->expectedBalance();
                @endphp
                <tr>
                  <td class="strong">{{ ucfirst($session->shift) }}</td>
                  <td>{{ $session->cashier?->name }}</td>
                  <td class="nowrap">{{ $session->opened_at->format('H:i') }}–{{ $session->closed_at?->format('H:i') ?? 'sekarang' }}</td>
                  <td class="num">{{ rupiah($session->opening_balance) }}</td>
                  <td class="num v in">{{ rupiah($sIn) }}</td>
                  <td class="num v out">{{ rupiah($sOut) }}</td>
                  <td class="num strong">{{ rupiah($sExpected) }}</td>
                  <td class="num">{{ $session->physical_balance !== null ? rupiah($session->physical_balance) : '—' }}</td>
                  <td class="num {{ (float) $session->difference < 0 ? 'text-red' : 'text-green' }}">{{ $session->difference !== null ? rupiah($session->difference) : '—' }}</td>
                  <td><span class="badge {{ $session->status === 'open' ? 'amber' : 'green' }}">{{ $session->status === 'open' ? 'Berjalan' : 'Tutup' }}</span></td>
                </tr>
              @empty
                <tr><td colspan="10"><div class="empty"><span class="ic">💰</span>Belum ada sesi kas hari ini.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="grid g4">
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Tunai masuk hari ini</span><div class="val" style="font-size:19px">{{ rupiah($recap['cash_in']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Tunai keluar</span><div class="val text-red" style="font-size:19px">{{ rupiah($recap['cash_out']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Non-tunai</span><div class="val text-blue" style="font-size:19px">{{ rupiah($recap['non_cash']) }}</div></div>
          <div class="card kpi" style="box-shadow:none"><span class="lbl">Total selisih</span><div class="val {{ $recap['difference'] == 0 ? 'text-green' : 'text-red' }}" style="font-size:19px">{{ rupiah($recap['difference']) }}</div></div>
        </div>
      </div>
    @endif

    @if($tab === 'riwayat')
      <div class="card-b tight">
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Kode sesi</th><th>Tanggal</th><th>Shift</th><th>Petugas</th><th class="num">Selisih</th><th>Catatan</th><th>Verifikasi</th></tr></thead>
            <tbody>
              @forelse($sessionHistory as $session)
                <tr>
                  <td class="mono">{{ $session->code }}</td>
                  <td>{{ tanggal($session->opened_at) }}</td>
                  <td>{{ ucfirst($session->shift) }}</td>
                  <td>{{ $session->cashier?->name }}</td>
                  <td class="num {{ (float) $session->difference < 0 ? 'text-red' : ((float) $session->difference > 0 ? 'text-green' : '') }}">
                    {{ $session->difference !== null ? rupiah($session->difference) : '—' }}
                  </td>
                  <td>{{ $session->notes ?: '—' }}</td>
                  <td>
                    @if($session->verified_by)
                      <span class="badge green">{{ $session->verifier?->name ?? 'Terverifikasi' }}</span>
                    @elseif($session->status === 'open')
                      <span class="badge amber">Berjalan</span>
                    @else
                      <span class="badge grey">—</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="7"><div class="empty"><span class="ic">💰</span>Belum ada riwayat sesi.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        {{ $sessionHistory->links('partials.pagination') }}
      </div>
    @endif

    @if($tab === 'setoran')
      <div class="card-b stack">
        @if(! $active)
          <div class="alert warn"><span class="ic">⚠️</span><div>Buka sesi kas terlebih dahulu sebelum mencatat setoran bank — setoran mengurangi kas fisik.</div></div>
        @endif
        <div class="grid g-2-1">
          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Riwayat setoran</h3></div>
            <div class="table-wrap">
              <table class="tbl">
                <thead><tr><th>Tanggal</th><th>Bank / Tujuan</th><th>Referensi</th><th class="num">Nominal</th><th>Petugas</th></tr></thead>
                <tbody>
                  @forelse($bankDeposits as $deposit)
                    <tr>
                      <td>{{ tanggal($deposit->deposit_date) }}</td>
                      <td class="strong">{{ $deposit->bank }}</td>
                      <td class="mono">{{ $deposit->reference ?: '—' }}</td>
                      <td class="num strong">{{ rupiah($deposit->amount) }}</td>
                      <td>{{ $deposit->creator?->name }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="5"><div class="empty"><span class="ic">🏦</span>Belum ada setoran.</div></td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

          <div class="card" style="box-shadow:none">
            <div class="card-h"><h3>Catat Setoran</h3></div>
            <form method="POST" action="{{ route('cash.deposit') }}" class="card-b stack">
              @csrf
              <div class="field"><label>Nominal</label><div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="1" step="1000" name="amount" required></div></div>
              <div class="field"><label>Bank / tujuan</label><input class="input" name="bank" required maxlength="80" placeholder="BRI — Rek. Operasional / Brankas"></div>
              <div class="field"><label>Tanggal</label><input class="input" type="date" name="deposit_date" value="{{ now()->toDateString() }}" required></div>
              <div class="field"><label>Referensi</label><input class="input" name="reference" maxlength="80" placeholder="No. transfer / bukti setoran"></div>
              <button class="btn btn-primary" @disabled(! $active)>Simpan setoran</button>
            </form>
          </div>
        </div>
      </div>
    @endif
  </div>

  <div class="modal-mask" id="modalMovement">
    <div class="modal">
      <form method="POST" action="{{ route('cash.movement') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-h">
          <div><h3>Mutasi Kas</h3><div class="sub">Kas masuk / keluar di luar transaksi otomatis</div></div>
          <button class="x" type="button" onclick="closeModal('modalMovement')">✕</button>
        </div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field">
              <label>Jenis</label>
              <select class="select" name="type" required>
                <option value="out">Kas keluar</option>
                <option value="in">Kas masuk</option>
              </select>
            </div>
            <div class="field">
              <label>Kategori</label>
              <select class="select" name="category" required>
                <option value="kas_kecil">Kas kecil / petty cash</option>
                <option value="pembelian">Pembelian</option>
                <option value="setoran_bank">Setoran bank</option>
                <option value="koreksi">Koreksi</option>
                <option value="lain_lain">Lain-lain</option>
              </select>
            </div>
            <div class="field">
              <label>Nominal</label>
              <div class="money-input"><span class="rp">Rp</span><input class="input" type="number" min="1" step="1000" name="amount" required></div>
            </div>
            <div class="field">
              <label>Lampiran nota</label>
              <input class="input" type="file" name="attachment" accept="image/*,.pdf">
              <span class="hint">Wajib untuk kas keluar kategori kas kecil.</span>
            </div>
            <div class="field full">
              <label>Keterangan <span class="req">*</span></label>
              <input class="input" name="description" required maxlength="160" placeholder="Contoh: beli gas dan air galon dapur">
            </div>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn" type="button" onclick="closeModal('modalMovement')">Batal</button>
          <button class="btn btn-primary">Simpan mutasi</button>
        </div>
      </form>
    </div>
  </div>
@endsection
