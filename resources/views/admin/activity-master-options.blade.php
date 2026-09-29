<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Master Aktivitas — RuangKerja</title>@fonts @vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
@include('partials.portal-header')
<main class="form-page admin-page">
    <section class="form-page-heading"><div><p class="eyebrow">TATA KELOLA DATA</p><h1>Master aktivitas</h1><p>Kelola kategori dan sifat pekerjaan tanpa menghilangkan jejak data historis.</p></div></section>
    @include('partials.feedback')

    <section class="form-card master-filter-card">
        <form method="GET" class="master-filters">
            <label class="field"><span>Jenis master</span><select name="type"><option value="">Semua jenis</option><option value="category" @selected(($filters['type']??'')==='category')>Kategori</option><option value="work_type" @selected(($filters['type']??'')==='work_type')>Sifat pekerjaan</option></select></label>
            <label class="field"><span>Status</span><select name="status"><option value="">Semua status</option><option value="active" @selected(($filters['status']??'')==='active')>Aktif</option><option value="inactive" @selected(($filters['status']??'')==='inactive')>Nonaktif</option><option value="merged" @selected(($filters['status']??'')==='merged')>Sudah digabung</option></select></label>
            <label class="field"><span>Cari opsi</span><input name="search" value="{{ $filters['search']??'' }}" placeholder="Nama kategori atau sifat"></label>
            <button class="button secondary">Terapkan</button>
            <a href="{{ route('admin.activity-master-options.index') }}" class="text-button">Reset</a>
        </form>
    </section>

    <section class="master-option-list" aria-label="Daftar master aktivitas">
        @forelse($options as $option)
            <article class="master-option-card">
                <div class="master-option-main">
                    <span class="master-type-badge {{ $option->type }}">{{ $option->type === 'category' ? 'Kategori' : 'Sifat pekerjaan' }}</span>
                    <div><h2>{{ $option->label }}</h2><p>{{ $option->usage_count }} aktivitas menggunakan opsi ini · Dibuat oleh <strong>{{ $option->creator?->name ?? 'Sistem' }}</strong> pada {{ $option->created_at->translatedFormat('d M Y H:i') }}@if($option->merged_at) · Digabung oleh <strong>{{ $option->merger?->name ?? 'Sistem' }}</strong> pada {{ $option->merged_at->translatedFormat('d M Y H:i') }}@endif</p></div>
                </div>
                <div class="master-option-state">
                    @if($option->merged_into_id)<span class="submission-state revision_required">Digabung ke {{ $option->mergedInto?->label ?? 'opsi lain' }}</span>
                    @elseif($option->is_active)<span class="submission-state approved">Aktif</span>
                    @else<span class="submission-state draft">Nonaktif</span>@endif
                </div>

                @if(!$option->merged_into_id)
                    <div class="master-option-actions">
                        <details><summary>Ganti nama</summary><form method="POST" action="{{ route('admin.activity-master-options.update',$option) }}">@csrf @method('PUT')<label class="field"><span>Nama opsi</span><input name="label" value="{{ $option->label }}" maxlength="100" required></label><button class="button secondary">Simpan nama</button></form></details>
                        <form method="POST" action="{{ route('admin.activity-master-options.status',$option) }}">@csrf @method('PUT')<input type="hidden" name="is_active" value="{{ $option->is_active ? 0 : 1 }}"><button class="button {{ $option->is_active ? 'danger-subtle' : 'secondary' }}">{{ $option->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                        @php($targets=$mergeTargets->get($option->type,collect())->where('id','!=',$option->id))
                        @if($targets->isNotEmpty())<details><summary>Gabungkan</summary><form method="POST" action="{{ route('admin.activity-master-options.merge',$option) }}">@csrf<label class="field"><span>Alihkan seluruh pemakaian ke</span><select name="target_id" required><option value="">Pilih opsi tujuan</option>@foreach($targets as $target)<option value="{{ $target->id }}">{{ $target->label }}</option>@endforeach</select></label><p>Opsi ini akan dinonaktifkan dan tidak dapat diaktifkan kembali.</p><button class="button primary">Konfirmasi merge</button></form></details>@endif
                    </div>
                @endif
            </article>
        @empty
            <div class="empty-period"><h2>Opsi tidak ditemukan</h2><p>Ubah filter atau cari menggunakan nama lain.</p></div>
        @endforelse
    </section>
    {{ $options->links() }}
</main>
</body>
</html>
