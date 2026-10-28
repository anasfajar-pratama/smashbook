@extends('layouts.app')

@section('title', 'Pengguna & Peran')
@section('crumb', 'Akun pribadi per peran · tanpa akun sharing')

@section('topbar-actions')
  <button class="btn btn-primary btn-sm" type="button" onclick="openModal('modalUser')">+ Pengguna</button>
@endsection

@section('content')
  <div class="alert info">
    <span class="ic">ℹ️</span>
    <div>Satu kasir satu akun agar selisih kas dapat dipertanggungjawabkan. PIN 4–6 digit dipakai untuk approval void, diskon besar, dan selisih kas.</div>
  </div>

  <div class="card">
    <div class="card-h"><h3>Daftar pengguna</h3><div class="right"><span class="badge grey">{{ $users->count() }} akun</span></div></div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Nama</th><th>Email</th><th>Telepon</th><th>Peran</th><th>Status</th><th>Shift aktif</th><th>Login terakhir</th><th></th></tr></thead>
        <tbody>
          @foreach($users as $user)
            <tr>
              <td><div class="member-cell"><div class="avatar-sm">{{ $user->initials }}</div><div><div class="strong">{{ $user->name }}</div><div class="muted-text mono">#{{ $user->id }}</div></div></div></td>
              <td>{{ $user->email }}</td>
              <td>{{ $user->phone ?: '—' }}</td>
              <td><span class="badge {{ $user->isOwner() ? 'dark' : ($user->role === 'admin' ? 'violet' : ($user->role === 'kasir' ? 'blue' : 'grey')) }}">{{ $user->role_label }}</span></td>
              <td><span class="badge {{ $user->is_active ? 'green' : 'red' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td>
                @if($user->activeCashSession)
                  <span class="badge amber">{{ ucfirst($user->activeCashSession->shift) }} · {{ rupiah($user->activeCashSession->expectedBalance()) }}</span>
                @else
                  <span class="muted-text">—</span>
                @endif
              </td>
              <td>{{ $user->last_login_at ? tanggal($user->last_login_at, 'j M Y H:i') : '—' }}</td>
              <td class="right">
                <button class="btn btn-sm" type="button"
                  data-user-edit="{{ $user->id }}"
                  data-name="{{ $user->name }}"
                  data-email="{{ $user->email }}"
                  data-phone="{{ $user->phone }}"
                  data-role="{{ $user->role }}"
                  data-active="{{ $user->is_active ? 1 : 0 }}"
                  onclick="openUserEdit(this)">Ubah</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-mask" id="modalUser">
    <div class="modal">
      <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="modal-h"><div><h3>Pengguna Baru</h3><div class="sub">Password minimal 8 karakter.</div></div><button class="x" type="button" onclick="closeModal('modalUser')">✕</button></div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Nama</label><input class="input" name="name" required maxlength="120"></div>
            <div class="field"><label>Email</label><input class="input" type="email" name="email" required maxlength="120"></div>
            <div class="field"><label>Telepon</label><input class="input" name="phone" maxlength="30"></div>
            <div class="field"><label>Peran</label><select class="select" name="role" required>@foreach(\App\Models\User::ROLES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label>Password</label><input class="input" type="password" name="password" required minlength="8"></div>
            <div class="field"><label>PIN approval (opsional)</label><input class="input" type="password" name="pin" maxlength="6" inputmode="numeric"><span class="hint">4–6 digit angka.</span></div>
            <div class="field full"><label class="check-row"><input type="checkbox" name="is_active" value="1" checked> Akun aktif</label></div>
          </div>
        </div>
        <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalUser')">Batal</button><button class="btn btn-primary">Simpan pengguna</button></div>
      </form>
    </div>
  </div>

  <div class="modal-mask" id="modalUserEdit">
    <div class="modal">
      <form method="POST" id="userEditForm">
        @csrf @method('PUT')
        <div class="modal-h"><div><h3>Ubah Pengguna</h3><div class="sub">Kosongkan password/PIN bila tidak ingin mengubah.</div></div><button class="x" type="button" onclick="closeModal('modalUserEdit')">✕</button></div>
        <div class="modal-b">
          <div class="form-grid">
            <div class="field"><label>Nama</label><input class="input" name="name" id="ue_name" required maxlength="120"></div>
            <div class="field"><label>Email</label><input class="input" type="email" name="email" id="ue_email" required maxlength="120"></div>
            <div class="field"><label>Telepon</label><input class="input" name="phone" id="ue_phone" maxlength="30"></div>
            <div class="field"><label>Peran</label><select class="select" name="role" id="ue_role" required>@foreach(\App\Models\User::ROLES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label>Password baru</label><input class="input" type="password" name="password" minlength="8"></div>
            <div class="field"><label>PIN baru</label><input class="input" type="password" name="pin" maxlength="6" inputmode="numeric"></div>
            <div class="field full"><label class="check-row"><input type="checkbox" name="is_active" id="ue_active" value="1"> Akun aktif</label></div>
          </div>
        </div>
        <div class="modal-f"><button class="btn" type="button" onclick="closeModal('modalUserEdit')">Batal</button><button class="btn btn-primary">Simpan perubahan</button></div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  function openUserEdit(button) {
    const form = document.getElementById('userEditForm');
    form.action = '{{ url('admin/users') }}/' + button.dataset.userEdit;
    document.getElementById('ue_name').value = button.dataset.name;
    document.getElementById('ue_email').value = button.dataset.email;
    document.getElementById('ue_phone').value = button.dataset.phone || '';
    document.getElementById('ue_role').value = button.dataset.role;
    document.getElementById('ue_active').checked = button.dataset.active === '1';
    openModal('modalUserEdit');
  }
</script>
@endpush
