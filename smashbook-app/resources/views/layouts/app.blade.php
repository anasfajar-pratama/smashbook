<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Dashboard') · {{ setting('venue_name', 'SmashBook') }}</title>
  <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/custom.css') }}">
  @stack('head')
</head>
<body>

@php
  $user = auth()->user();
  $activeSession = $user?->activeCashSession;
  $navHolds = \App\Models\Booking::query()->whereDate('booking_date', now()->toDateString())->where('status', 'held')->count();
  $navPendingPo = $user?->isAdmin() ? \App\Models\PurchaseOrder::query()->where('status', 'submitted')->count() : 0;
  $navLowStock = \App\Models\Product::query()->lowStock()->count();
@endphp

<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="logo">SB</div>
      <div>
        <div class="name">SmashBook</div>
        <div class="sub">{{ setting('venue_name', 'GOR Smash Arena') }}</div>
      </div>
    </div>

    <nav class="nav">
      <div class="nav-title">Utama</div>
      <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
        Dashboard
      </a>
      <a href="{{ route('bookings.index') }}" class="{{ request()->routeIs('bookings.*') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
        Booking Lapangan
        @if($navHolds > 0)<span class="tag">{{ $navHolds }}</span>@endif
      </a>
      <a href="{{ route('courts.index') }}" class="{{ request()->routeIs('courts.*') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="10" rx="1"/><path d="M12 7v10"/></svg>
        Lapangan &amp; Tarif
      </a>

      @if($user?->isCashier())
        <div class="nav-title">Penjualan</div>
        <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.*') ? 'active' : '' }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12h12L22 7H6"/></svg>
          POS Kasir
        </a>
      @endif

      <div class="nav-title">Keuangan</div>
      <a href="{{ route('cash.index') }}" class="{{ request()->routeIs('cash.*') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
        Kas Harian
      </a>
      @if($user?->isAdmin())
        <a href="{{ route('purchase-orders.index') }}" class="{{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>
          Quick PO &amp; Biaya
          @if($navPendingPo > 0)<span class="tag">{{ $navPendingPo }}</span>@endif
        </a>
        <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
          Laporan
        </a>
      @endif

      <div class="nav-title">Persediaan</div>
      <a href="{{ route('inventory.index') }}" class="{{ request()->routeIs('inventory.*') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 21V13"/></svg>
        Stock Barang
        @if($navLowStock > 0 && $user?->isAdmin())<span class="tag" style="background:#f59e0b">{{ $navLowStock }}</span>@endif
      </a>

      <div class="nav-title">Master</div>
      @if($user?->isCashier())
        <a href="{{ route('members.index') }}" class="{{ request()->routeIs('members.*') ? 'active' : '' }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
          Member
        </a>
      @endif

      @if($user?->isAdmin())
        <div class="nav-title">Pengaturan</div>
        @if($user?->isOwner())
          <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Pengguna
          </a>
        @endif
        <a href="{{ route('admin.audit') }}" class="{{ request()->routeIs('admin.audit*') ? 'active' : '' }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          Audit Log
        </a>
        @if($user?->isOwner())
          <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
            Pengaturan
          </a>
        @endif
      @endif
    </nav>

    <div class="sidebar-foot">
      <div style="margin-bottom:8px">{{ $user?->name }} · {{ $user?->role_label }}</div>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn-sm" style="width:100%;background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.12);color:#cbd5e1">Keluar</button>
      </form>
      <div style="margin-top:8px">SmashBook v1.0 · {{ now()->format('Y') }}</div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div>
        <h1>@yield('title', 'Dashboard')</h1>
        <div class="crumb">@yield('crumb', now()->locale('id')->translatedFormat('l, j F Y'))</div>
      </div>
      <div class="spacer"></div>

      @if($activeSession)
        <div class="shift-pill"><span class="dot"></span> Shift {{ ucfirst($activeSession->shift) }} · Kas {{ rupiah($activeSession->expectedBalance()) }}</div>
      @elseif($user?->isCashier())
        <a href="{{ route('cash.index') }}" class="shift-pill" style="text-decoration:none"><span class="dot amber"></span> Belum ada shift · Buka kas</a>
      @endif

      @yield('topbar-actions')

      <div class="user">
        <div class="avatar">{{ $user?->initials }}</div>
        <div>
          <div class="nm">{{ $user?->name }}</div>
          <div class="rl">{{ $user?->role_label }}@if($activeSession) · Shift {{ ucfirst($activeSession->shift) }}@endif</div>
        </div>
      </div>
    </header>

    <div class="content">
      @if(session('success'))
        <div class="alert ok"><span class="ic">✓</span><div>{{ session('success') }}</div></div>
      @endif
      @if(session('error'))
        <div class="alert err"><span class="ic">⚠️</span><div>{{ session('error') }}</div></div>
      @endif
      @if($errors->any())
        <div class="alert err">
          <span class="ic">⚠️</span>
          <div>
            <b>Periksa kembali input berikut:</b>
            <ul>
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @yield('content')
    </div>
  </div>
</div>

@stack('modals')

<script>
  window.SMASHBOOK = { quoteUrl: @json(route('bookings.quote')) };
</script>
<script src="{{ asset('assets/app.js') }}"></script>
@stack('scripts')
</body>
</html>
