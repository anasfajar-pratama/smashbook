<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk · SmashBook</title>
  <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/custom.css') }}">
</head>
<body>

<div class="login-wrap">
  <div class="login-left">
    <div class="brand">
      <div class="logo">SB</div>
      <div>
        <div class="name">SmashBook</div>
        <div class="sub">{{ setting('venue_name', 'GOR Smash Arena') }}</div>
      </div>
    </div>

    <div>
      <h2>Satu sistem untuk jadwal, penjualan, kas, dan stok lapangan.</h2>
      <p class="lead">Booking anti bentrok, POS dengan stok otomatis, kas per shift, Quick PO berjenjang, dan kartu stok yang konsisten.</p>
    </div>

    <div class="feat">
      <div class="f"><div class="ic">📅</div><div><b>Jadwal anti bentrok</b><small>Slot dikunci di database dengan transaksi &amp; unique index.</small></div></div>
      <div class="f"><div class="ic">🧾</div><div><b>POS &amp; struk thermal</b><small>Stok turun saat dibayar, struk siap cetak 58/80 mm.</small></div></div>
      <div class="f"><div class="ic">💰</div><div><b>Kas per shift</b><small>Modal awal, mutasi otomatis, hitung fisik, selisih terverifikasi.</small></div></div>
      <div class="f"><div class="ic">📦</div><div><b>Quick PO &amp; stok</b><small>Approval berjenjang, nota wajib, HPP rata-rata bergerak.</small></div></div>
    </div>
  </div>

  <div class="login-right">
    <div class="login-box">
      <div class="stack" style="gap:6px">
        <h3>Masuk ke sistem</h3>
        <p class="muted-text">Gunakan akun pribadi Anda. Satu kasir satu akun — agar selisih kas dapat dipertanggungjawabkan.</p>
      </div>

      @if($errors->any())
        <div class="alert err" style="margin-top:14px">
          <span class="ic">⚠️</span>
          <div>{{ $errors->first() }}</div>
        </div>
      @endif

      <form method="POST" action="{{ route('login.store') }}" class="stack" style="margin-top:16px">
        @csrf
        <div class="field">
          <label>Email</label>
          <input class="input" type="email" name="email" id="email" value="{{ old('email', app()->environment('local') ? 'dewi@smasharena.id' : '') }}" autocomplete="username" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input class="input" type="password" name="password" id="pass" value="{{ app()->environment('local') ? 'smashbook123' : '' }}" autocomplete="current-password" required>
        </div>
        <div class="field">
          <label>PIN approval (opsional)</label>
          <input class="input" type="password" name="pin" id="pin" placeholder="4–6 digit, untuk approval cepat" maxlength="6">
          <span class="hint">Dipakai saat void, diskon besar, atau tutup shift berselisih.</span>
        </div>
        <div class="row between">
          <label class="check-row"><input type="checkbox" name="remember" checked> Ingat perangkat ini</label>
          <span class="muted-text">Hubungi Owner bila lupa password</span>
        </div>
        <button class="btn btn-primary btn-lg btn-block" type="submit">Masuk</button>
      </form>

      @if(app()->environment('local'))
        <div class="divider" style="margin:18px 0"></div>
        <p class="muted-text" style="margin-bottom:8px">Akun demo:</p>
        <div class="demo-acc">
          <button type="button" onclick="fillLogin('owner@smasharena.id')"><b>Owner</b>Semua laporan &amp; approval</button>
          <button type="button" onclick="fillLogin('admin@smasharena.id')"><b>Admin</b>Master, stok, PO, laporan</button>
          <button type="button" onclick="fillLogin('dewi@smasharena.id')"><b>Kasir</b>POS, kas, booking</button>
          <button type="button" onclick="fillLogin('operator@smasharena.id')"><b>Operator</b>Jadwal &amp; check-in</button>
        </div>
        <p class="muted-text" style="margin-top:10px">Password demo: <span class="mono">smashbook123</span> · PIN Owner: <span class="mono">1234</span></p>
      @endif
      <p class="muted-text" style="text-align:center;margin-top:18px">SmashBook v1.0 · Sistem pengelolaan lapangan badminton</p>
    </div>
  </div>
</div>

<script>
  function fillLogin(email) {
    document.getElementById('email').value = email;
    document.getElementById('pass').value = 'smashbook123';
    document.getElementById('pin').value = '';
  }
</script>
</body>
</html>
