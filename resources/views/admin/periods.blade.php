<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Periode — RuangKerja</title>@fonts @vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
@include('partials.portal-header')
<main class="form-page admin-page">
    <section class="form-page-heading"><div><p class="eyebrow">KONTROL PERIODE</p><h1>Periode dan standar kapasitas</h1><p>Pola dan jam kerja mengikuti profil masing-masing anggota. Periode menetapkan persentase waktu produktif.</p></div></section>
    @include('partials.feedback')
    <section class="form-card">
        <div class="form-card-heading"><span>+</span><div><h2>Buat periode draft</h2><p>Hari kerja aktual dihitung otomatis dari pola rotasi setiap anggota.</p></div></div>
        <form method="POST" action="{{ route('admin.periods.store') }}" class="period-policy-form">@csrf
            <label class="field"><span>Awal periode</span><input type="date" name="period_start" value="{{ old('period_start') }}" required></label>
            <label class="field"><span>Batas pengisian</span><input type="date" name="submission_deadline" value="{{ old('submission_deadline') }}" required></label>
            <label class="field"><span>Waktu produktif</span><div class="input-suffix"><input type="number" name="standard_productive_percentage" min="1" max="100" step="0.1" value="{{ old('standard_productive_percentage',85) }}" required><span>%</span></div></label>
            <label class="field"><span>Catatan kebijakan <em>opsional</em></span><textarea name="capacity_policy_note" rows="2" maxlength="2000" placeholder="Cuti dan libur nasional tidak mengurangi kapasitas dasar">{{ old('capacity_policy_note') }}</textarea></label>
            <button class="button primary">Buat periode</button>
        </form>
    </section>
    <div class="period-list">
        @foreach($periods as $period)
            <article class="period-record">
                <div class="period-date"><span>{{ $period->period_start->translatedFormat('M') }}</span><strong>{{ $period->period_start->format('Y') }}</strong></div>
                <div><h2>{{ $period->period_start->translatedFormat('F Y') }}</h2><p>Batas {{ $period->submission_deadline->translatedFormat('d F Y') }} · {{ $period->submissions_count }} pengajuan</p><small>Pola kerja per anggota × {{ number_format($period->standard_productive_percentage,1,',','.') }}% produktif</small>@if($period->reopen_reason)<small> · Reopen: {{ $period->reopen_reason }}</small>@endif</div>
                <span class="submission-state {{ $period->status==='open'?'approved':($period->status==='locked'?'revision_required':'draft') }}">{{ match($period->status){'open'=>'Terbuka','locked'=>'Terkunci',default=>'Draft'} }}</span>
                <div class="record-actions">
                    @if($period->status === 'draft')
                        <details><summary>Ubah periode</summary><form method="POST" action="{{ route('admin.periods.update',$period) }}">@csrf @method('PUT')
                            <label class="field"><span>Awal periode</span><input type="date" name="period_start" value="{{ $period->period_start->toDateString() }}" required></label>
                            <label class="field"><span>Batas pengisian</span><input type="date" name="submission_deadline" value="{{ $period->submission_deadline->toDateString() }}" required></label>
                            <button class="button secondary" type="submit">Simpan periode</button>
                        </form></details>
                        <details><summary>Ubah standar</summary><form method="POST" action="{{ route('admin.periods.capacity-standard.update',$period) }}">@csrf @method('PUT')
                            <label class="field"><span>Produktif</span><input type="number" name="standard_productive_percentage" min="1" max="100" step="0.1" value="{{ $period->standard_productive_percentage }}" required></label>
                            <label class="field"><span>Catatan kebijakan</span><textarea name="capacity_policy_note" maxlength="2000">{{ $period->capacity_policy_note }}</textarea></label>
                            <button class="button secondary" type="submit">Simpan standar</button>
                        </form></details>
                    @endif
                    @if(in_array($period->status,['draft','locked']))<form method="POST" action="{{ route('admin.periods.open',$period) }}">@csrf<button class="button secondary">Buka</button></form>@endif
                    @if($period->status==='open')<form method="POST" action="{{ route('admin.periods.lock',$period) }}">@csrf<button class="button primary">Kunci periode</button></form>@endif
                    @if($period->status==='locked')<details><summary>Buka kembali</summary><form method="POST" action="{{ route('admin.periods.reopen',$period) }}">@csrf<label class="field"><span>Alasan pembukaan kembali</span><textarea name="reason" minlength="10" required></textarea></label><button class="button secondary">Konfirmasi reopen</button></form></details>@endif
                </div>
            </article>
        @endforeach
    </div>
</main>
</body>
</html>
