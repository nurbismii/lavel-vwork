<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Validasi Pengajuan — RuangKerja</title>@fonts @vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
<header class="portal-header"><a href="{{ route('dashboard') }}" class="portal-brand"><span class="brand-mark"><svg viewBox="0 0 24 24"><path d="M5 17.5V12m7 5.5V6m7 11.5V9"/></svg></span><strong>RuangKerja</strong></a><a href="{{ route('dashboard') }}" class="button secondary">← Kembali ke dashboard</a></header>
<main class="form-page review-page">
    <section class="form-page-heading"><div><p class="eyebrow">VALIDASI ATASAN</p><h1>Tinjau pengajuan tim</h1><p>Periksa kapasitas, aktivitas, duplikasi, serta konteks sebelum mengambil keputusan.</p></div><span class="submission-state submitted">{{ $items->where('submission.status',\App\Enums\SubmissionStatus::Submitted)->count() }} menunggu</span></section>
    @if(session('success'))<div class="form-alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="form-alert error">{{ $errors->first() }}</div>@endif
    <div class="review-list">
        @forelse($items as $item)
        @php($submission=$item['submission']) @php($metrics=$item['metrics'])
        <article class="review-card">
            <div class="review-person"><span class="avatar avatar-blue">{{ collect(explode(' ',$submission->user->name))->map(fn($w)=>mb_substr($w,0,1))->take(2)->join('') }}</span><div><h2>{{ $submission->user->name }}</h2><p>{{ $submission->user->position }} · {{ $submission->workPeriod->period_start->translatedFormat('F Y') }}</p></div><span class="submission-state {{ $submission->status->value }}">{{ $submission->status->label() }}</span></div>
            <div class="review-metrics"><div><span>Kapasitas efektif</span><strong>{{ number_format($metrics['effective_minutes']/60,1,',','.') }} jam</strong></div><div><span>Waktu aktual</span><strong>{{ number_format($metrics['required_minutes']/60,1,',','.') }} jam</strong></div><div><span>Utilisasi</span><strong>{{ $metrics['utilization'] !== null ? number_format($metrics['utilization'],1,',','.') .'%' : '—' }}</strong></div><div><span>Status beban</span><strong class="status-badge {{ $metrics['status']->value }}"><i></i>{{ $metrics['status']->label() }}</strong></div></div>
            @if($submission->capacity)<details><summary>Lihat dasar kapasitas</summary><div class="review-activities"><div><span>Pola kerja</span><strong>{{ $submission->capacity->cycle_work_days ? $submission->capacity->cycle_work_days.':'.$submission->capacity->cycle_off_days.' · ' : '' }}{{ $submission->capacity->work_days }} hari aktual × {{ $submission->capacity->hours_per_day }} jam</strong></div><div><span>Kapasitas bruto</span><strong>{{ number_format($submission->capacity->gross_minutes/60,1,',','.') }} jam</strong></div><div><span>Waktu produktif</span><strong>{{ number_format($submission->capacity->productive_percentage,1,',','.') }}%</strong></div></div></details>@endif
            <details><summary>Lihat {{ $submission->activities->count() }} catatan aktivitas aktual</summary><div class="review-activities">@foreach($submission->activities as $activity)<div><span>{{ $activity->name }} <small>Aktivitas {{ $activity->activity_date?->translatedFormat('d M Y') ?? 'historis' }} · Dicatat {{ $activity->created_at->translatedFormat('d M Y H:i') }} · <i class="timeliness-badge {{ $activity->timelinessClass() }}">{{ $activity->timelinessLabel() }}</i> · {{ $activity->workTypeLabel() }} · {{ number_format($activity->monthly_volume,1,',','.') }} {{ $activity->unit }}</small></span><strong>{{ $activity->required_minutes }} menit</strong></div>@endforeach</div></details>
            @if($submission->status === \App\Enums\SubmissionStatus::Submitted)
            <form method="POST" action="{{ route('reviews.update',$submission) }}" class="review-form">@csrf @method('PUT')<label class="field"><span>Catatan validasi</span><input name="note" placeholder="Wajib ketika meminta revisi" maxlength="2000"></label><div><button class="button secondary" name="status" value="revision_required" type="submit">Minta revisi</button><button class="button primary" name="status" value="approved" type="submit">Setujui data</button></div></form>
            @elseif($submission->status === \App\Enums\SubmissionStatus::Approved)
            <form method="POST" action="{{ route('reviews.update',$submission) }}" class="review-form">@csrf @method('PUT')<label class="field"><span>Alasan membuka revisi</span><input name="note" placeholder="Wajib diisi agar perubahan dapat ditelusuri" maxlength="2000" required></label><div><button class="button secondary" name="status" value="revision_required" type="submit">Buka untuk revisi</button></div></form>
            @elseif($submission->review_note)<div class="review-note"><strong>Catatan terakhir</strong><p>{{ $submission->review_note }}</p></div>@endif
        </article>
        @empty<div class="empty-period"><h2>Tidak ada pengajuan</h2><p>Pengajuan anggota yang berada dalam kewenangan Anda akan muncul di sini.</p></div>@endforelse
    </div>
</main>
</body></html>
