<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Organisasi — RuangKerja</title>@fonts @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body>
    @include('partials.portal-header')
    <main class="form-page admin-page">
        <section class="form-page-heading">
            <div>
                <p class="eyebrow">ADMINISTRASI</p>
                <h1>Organisasi dan pengguna</h1>
                <p>Kelola unit, identitas, role, atasan, dan status akses tanpa menghapus riwayat lama.</p>
            </div><span class="submission-state approved">{{ $users->total() }} pengguna</span>
        </section>
        @include('partials.feedback')
        <div class="admin-columns">
            <section class="form-card">
                <div class="form-card-heading"><span>1</span>
                    <div>
                        <h2>Tambah unit</h2>
                        <p>Kode unit harus unik dan stabil.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.units.store') }}" class="compact-form">@csrf<label class="field"><span>Kode</span><input name="code" maxlength="30" placeholder="OPS" required></label><label class="field"><span>Nama unit</span><input name="name" maxlength="255" placeholder="Operasional" required></label><input type="hidden" name="is_active" value="1"><button class="button primary" type="submit">Tambah unit</button></form>
                <div class="record-list">@foreach($units as $unit)<details>
                        <summary><span><strong>{{ $unit->code }}</strong>{{ $unit->name }}</span><span class="submission-state {{ $unit->is_active?'approved':'draft' }}">{{ $unit->is_active?'Aktif':'Nonaktif' }}</span></summary>
                        <form method="POST" action="{{ route('admin.units.update',$unit) }}" class="compact-form">@csrf @method('PUT')<label class="field"><span>Kode</span><input name="code" value="{{ $unit->code }}" required></label><label class="field"><span>Nama</span><input name="name" value="{{ $unit->name }}" required></label><label class="field"><span>Status</span><select name="is_active">
                                    <option value="1" @selected($unit->is_active)>Aktif</option>
                                    <option value="0" @selected(!$unit->is_active)>Nonaktif</option>
                                </select></label><button class="button secondary">Simpan perubahan</button></form>
                    </details>@endforeach</div>
            </section>
            <section class="form-card">
                <div class="form-card-heading"><span>2</span>
                    <div>
                        <h2>Tambah pengguna</h2>
                        <p>Password awal minimal 10 karakter.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.users.store') }}" class="compact-form two-column">@csrf
                    <label class="field"><span>Nama</span><input name="name" required></label><label class="field"><span>Email</span><input type="email" name="email" required></label>
                    <label class="field"><span>ID anggota</span><input name="employee_code"></label><label class="field"><span>Posisi</span><input name="position" required></label>
                    <label class="field"><span>Unit</span><select name="organizational_unit_id" required>@foreach($units->where('is_active',true) as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select></label>
                    <label class="field"><span>Role</span><select name="role" required>@foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></label>
                    <label class="field"><span>Atasan</span><select name="supervisor_id">
                            <option value="">Tidak ada</option>@foreach($supervisors as $supervisor)<option value="{{ $supervisor->id }}">{{ $supervisor->name }}</option>@endforeach
                        </select></label><input type="hidden" name="is_active" value="1">
                    <label class="field"><span>Hari kerja dalam siklus</span><input type="number" name="cycle_work_days" min="1" max="365" value="{{ old('cycle_work_days',5) }}" required></label><label class="field"><span>Hari off dalam siklus</span><input type="number" name="cycle_off_days" min="1" max="365" value="{{ old('cycle_off_days',2) }}" required></label>
                    <label class="field"><span>Jam kerja per hari</span><input type="number" name="daily_work_hours" min="0.25" max="24" step="0.25" value="{{ old('daily_work_hours',8) }}" required></label><label class="field"><span>Hari pertama siklus kerja</span><input type="date" name="work_cycle_anchor_date" value="{{ old('work_cycle_anchor_date',today()->startOfWeek()->toDateString()) }}" required><small>Tanggal ini dihitung sebagai hari kerja pertama, bukan hari off.</small></label>
                    <label class="field"><span>Password awal</span><input type="password" name="password" minlength="10" required></label><label class="field"><span>Konfirmasi password</span><input type="password" name="password_confirmation" minlength="10" required></label>
                    <div class="span-2"><button class="button primary" type="submit">Buat akun pengguna</button></div>
                </form>
            </section>
        </div>
        <section class="form-card admin-table-card">
            <div class="panel-heading">
                <div>
                    <h2>Daftar pengguna</h2>
                    <p>Perubahan akses dicatat pada audit log.</p>
                </div>
                <form method="GET" class="filter-row"><input name="search" value="{{ request('search') }}" placeholder="Cari nama atau email"><select name="unit">
                        <option value="">Semua unit</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(request('unit')==$unit->id)>{{ $unit->name }}</option>@endforeach
                    </select><button class="button secondary">Filter</button></form>
            </div>
            <div class="record-list user-records">@foreach($users as $user)<details>
                    <summary><span class="person"><span class="avatar avatar-blue">{{ collect(explode(' ',$user->name))->map(fn($word)=>mb_substr($word,0,1))->take(2)->join('') }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->email }} · {{ $user->organizationalUnit?->name }} · {{ $user->cycle_work_days }}:{{ $user->cycle_off_days }} · {{ number_format($user->daily_work_hours,2,',','.') }} jam</small></span></span><span><span class="submission-state {{ $user->is_active?'approved':'draft' }}">{{ $user->role->label() }}</span></span></summary>
                    <form method="POST" action="{{ route('admin.users.update',$user) }}" class="compact-form user-edit-grid">@csrf @method('PUT')
                        <label class="field"><span>Nama</span><input name="name" value="{{ $user->name }}" required></label><label class="field"><span>Email</span><input type="email" name="email" value="{{ $user->email }}" required></label><label class="field"><span>ID anggota</span><input name="employee_code" value="{{ $user->employee_code }}"></label><label class="field"><span>Posisi</span><input name="position" value="{{ $user->position }}" required></label>
                        <label class="field"><span>Unit</span><select name="organizational_unit_id">@foreach($units as $unit)<option value="{{ $unit->id }}" @selected($user->organizational_unit_id===$unit->id)>{{ $unit->name }}</option>@endforeach</select></label><label class="field"><span>Role</span><select name="role">@foreach($roles as $role)<option value="{{ $role->value }}" @selected($user->role===$role)>{{ $role->label() }}</option>@endforeach</select></label><label class="field"><span>Atasan</span><select name="supervisor_id">
                                <option value="">Tidak ada</option>@foreach($supervisors->where('id','!=',$user->id) as $supervisor)<option value="{{ $supervisor->id }}" @selected($user->supervisor_id===$supervisor->id)>{{ $supervisor->name }}</option>@endforeach
                            </select></label><label class="field"><span>Status</span><select name="is_active">
                                <option value="1" @selected($user->is_active)>Aktif</option>
                                <option value="0" @selected(!$user->is_active)>Nonaktif</option>
                            </select></label>
                        <label class="field"><span>Hari kerja dalam siklus</span><input type="number" name="cycle_work_days" min="1" max="365" value="{{ $user->cycle_work_days }}" required></label><label class="field"><span>Hari off dalam siklus</span><input type="number" name="cycle_off_days" min="1" max="365" value="{{ $user->cycle_off_days }}" required></label><label class="field"><span>Jam kerja per hari</span><input type="number" name="daily_work_hours" min="0.25" max="24" step="0.25" value="{{ $user->daily_work_hours }}" required></label><label class="field"><span>Hari pertama siklus kerja</span><input type="date" name="work_cycle_anchor_date" value="{{ $user->work_cycle_anchor_date?->toDateString() }}" required></label>
                        <label class="field"><span>Password baru <em>opsional</em></span><input type="password" name="password" minlength="10"></label><label class="field"><span>Konfirmasi password</span><input type="password" name="password_confirmation"></label>
                        <div><button class="button secondary">Simpan pengguna</button></div>
                    </form>
                </details>@endforeach</div>
            {{ $users->links() }}
        </section>
    </main>
</body>

</html>