<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tindak Lanjut — RuangKerja</title>@fonts @vite(['resources/css/app.css','resources/js/app.js'])</head><body>@include('partials.portal-header')
<main class="form-page admin-page"><section class="form-page-heading"><div><p class="eyebrow">KEPUTUSAN KAPASITAS</p><h1>Tindak lanjut</h1><p>Catat redistribusi, perbaikan proses, otomatisasi, atau kajian kebutuhan tenaga.</p></div></section>@include('partials.feedback')
<section class="form-card">
    <div class="form-card-heading"><span>+</span><div><h2>Buat tindak lanjut</h2><p>Tetapkan owner dan target agar keputusan dapat dipantau.</p></div></div>
    <form method="POST" action="{{ route('follow-ups.store') }}" class="threshold-form">
        @csrf
        <label class="field"><span>Judul</span><input name="title" value="{{ old('title') }}" required></label>
        <label class="field"><span>Jenis tindakan</span><select name="action_type">
            @foreach(['redistribution' => 'Redistribusi', 'process_improvement' => 'Perbaikan proses', 'automation' => 'Otomatisasi', 'workforce_review' => 'Kajian kebutuhan tenaga'] as $key => $label)
                <option value="{{ $key }}" @selected(old('action_type') === $key)>{{ $label }}</option>
            @endforeach
        </select></label>
        <label class="field"><span>Unit</span><select name="organizational_unit_id">
            @foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('organizational_unit_id') === (string) $unit->id)>{{ $unit->name }}</option>@endforeach
        </select></label>
        <label class="field"><span>Owner</span><select name="owner_id" data-follow-up-owner required>
            <option value="">Pilih owner</option>
            @foreach($owners as $owner)<option value="{{ $owner->id }}" @selected((string) old('owner_id') === (string) $owner->id)>{{ $owner->name }}</option>@endforeach
        </select></label>
        <label class="field"><span>Terkait pengajuan</span>
            <select
                name="workload_submission_id"
                data-follow-up-submission
                data-url-template="{{ route('follow-ups.owner-submissions', ['owner' => 'OWNER_ID']) }}"
                data-selected="{{ old('workload_submission_id') }}"
                disabled
            ><option value="">Pilih owner terlebih dahulu</option></select>
            <small data-follow-up-submission-help>Daftar pengajuan akan mengikuti owner yang dipilih.</small>
        </label>
        <label class="field"><span>Target selesai</span><input type="date" name="target_date" value="{{ old('target_date') }}"></label>
        <label class="field span-2"><span>Deskripsi</span><textarea name="description" rows="3">{{ old('description') }}</textarea></label>
        <input type="hidden" name="status" value="open">
        <div><button class="button primary">Simpan tindak lanjut</button></div>
    </form>
</section>
<section class="form-card"><div class="panel-heading"><div><h2>Daftar tindakan</h2><p>{{ $actions->count() }} tindakan dalam cakupan Anda.</p></div><form method="GET" class="filter-row"><select name="status"><option value="">Semua status</option>@foreach(['open'=>'Terbuka','in_progress'=>'Berjalan','completed'=>'Selesai','cancelled'=>'Dibatalkan'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select><button class="button secondary">Filter</button></form></div><div class="action-list">@forelse($actions as $action)<article><div class="action-state {{ $action->status }}"></div><div><h3>{{ $action->title }}</h3><p>{{ $action->description }}</p><span>{{ $action->owner->name }} · {{ $action->target_date?->translatedFormat('d M Y') ?? 'Tanpa target' }} @if($action->submission)· {{ $action->submission->user->name }}@endif</span></div><span class="submission-state {{ $action->status==='completed'?'approved':'draft' }}">{{ match($action->status){'open'=>'Terbuka','in_progress'=>'Berjalan','completed'=>'Selesai',default=>'Dibatalkan'} }}</span><details><summary>Perbarui</summary><form method="POST" action="{{ route('follow-ups.update',$action) }}" class="compact-form">@csrf @method('PUT')<input type="hidden" name="title" value="{{ $action->title }}"><input type="hidden" name="action_type" value="{{ $action->action_type }}"><input type="hidden" name="organizational_unit_id" value="{{ $action->organizational_unit_id }}"><input type="hidden" name="owner_id" value="{{ $action->owner_id }}"><input type="hidden" name="workload_submission_id" value="{{ $action->workload_submission_id }}"><input type="hidden" name="target_date" value="{{ $action->target_date?->toDateString() }}"><input type="hidden" name="description" value="{{ $action->description }}"><label class="field"><span>Status</span><select name="status">@foreach(['open'=>'Terbuka','in_progress'=>'Berjalan','completed'=>'Selesai','cancelled'=>'Dibatalkan'] as $key=>$label)<option value="{{ $key }}" @selected($action->status===$key)>{{ $label }}</option>@endforeach</select></label><button class="button secondary">Simpan status</button></form></details></article>@empty<div class="empty-activities">Belum ada tindak lanjut.</div>@endforelse</div></section>
</main></body></html>
