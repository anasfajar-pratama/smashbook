@extends('layouts.app')

@section('title', 'Pengaturan Sistem')
@section('crumb', 'Identitas GOR, operasional, POS, dan threshold approval')

@section('content')
  <div class="alert warn">
    <span class="ic">⚠️</span>
    <div>Perubahan pengaturan berlaku segera. Tarif dan HPP pada transaksi lama tetap memakai snapshot saat transaksi dibuat.</div>
  </div>

  <form method="POST" action="{{ route('admin.settings.update') }}" class="stack">
    @csrf
    @foreach($groups as $group => $fields)
      <div class="card">
        <div class="card-h"><h3>{{ $group }}</h3></div>
        <div class="card-b">
          <div class="form-grid">
            @foreach($fields as $key => $field)
              <div class="field {{ $field['type'] === 'textarea' ? 'full' : '' }}">
                <label>{{ $field['label'] }}</label>
                @if($field['type'] === 'textarea')
                  <textarea class="input" name="{{ $key }}" rows="2">{{ old($key, $values[$key] ?? '') }}</textarea>
                @else
                  <input class="input" type="{{ $field['type'] }}" name="{{ $key }}" value="{{ old($key, $values[$key] ?? '') }}">
                @endif
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endforeach

    <div class="row justify-end">
      <button class="btn btn-primary btn-lg">Simpan pengaturan</button>
    </div>
  </form>
@endsection
