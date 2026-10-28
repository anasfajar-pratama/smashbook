@extends('layouts.app')

@section('title', 'Audit Log')
@section('crumb', 'Jejak aksi sensitif · tidak dapat dihapus kasir')

@section('content')
  <div class="card">
    <div class="card-b">
      <form method="GET" action="{{ route('admin.audit') }}" class="filter-bar">
        <div class="field" style="min-width:180px">
          <label>Pengguna</label>
          <select class="select" name="user">
            <option value="">Semua pengguna</option>
            @foreach($users as $user)
              <option value="{{ $user->id }}" @selected(request('user') == $user->id)>{{ $user->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field" style="min-width:170px">
          <label>Entitas</label>
          <select class="select" name="entity">
            <option value="">Semua entitas</option>
            @foreach($entities as $entity)
              <option value="{{ $entity }}" @selected(request('entity') === $entity)>{{ $entity }}</option>
            @endforeach
          </select>
        </div>
        <div class="field" style="min-width:140px"><label>Dari</label><input class="input" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="field" style="min-width:140px"><label>s/d</label><input class="input" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="field"><label>&nbsp;</label><button class="btn">Filter</button></div>
      </form>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>Alasan</th><th>IP</th><th></th></tr></thead>
        <tbody>
          @forelse($logs as $log)
            <tr>
              <td class="nowrap">{{ $log->created_at?->format('j M Y H:i:s') }}</td>
              <td>{{ $log->user?->name ?? 'Sistem' }}</td>
              <td><span class="badge {{ str_contains($log->action, 'void') || str_contains($log->action, 'cancel') ? 'red' : (str_contains($log->action, 'create') ? 'green' : 'blue') }}" title="{{ $log->action }}">{{ audit_label($log->action) }}</span></td>
              <td class="mono">{{ $log->entity }}{{ $log->entity_id ? ' #' . $log->entity_id : '' }}</td>
              <td>{{ $log->reason ?: '—' }}</td>
              <td class="mono">{{ $log->ip ?: '—' }}</td>
              <td class="right">
                @if($log->before_json || $log->after_json)
                  <details>
                    <summary class="muted-text" style="cursor:pointer">Detail</summary>
                    <div style="text-align:left;max-width:420px;margin-top:6px" class="mono-small">
                      @if($log->before_json)<div><b>Before:</b><pre style="white-space:pre-wrap;margin:2px 0">{{ json_encode($log->before_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>@endif
                      @if($log->after_json)<div><b>After:</b><pre style="white-space:pre-wrap;margin:2px 0">{{ json_encode($log->after_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>@endif
                    </div>
                  </details>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty"><span class="ic">📋</span>Belum ada audit log.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $logs->links('partials.pagination') }}
  </div>
@endsection
