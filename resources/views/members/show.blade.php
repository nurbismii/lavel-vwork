<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Detail {{ $member->name }} — RuangKerja</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@include('partials.portal-header')
<main class="form-page admin-page member-detail-page">
    <section class="form-page-heading">
        <div><p class="eyebrow">SUMBER PERHITUNGAN</p><h1>{{ $member->name }}</h1><p>{{ $member->employee_code }} · {{ $member->position ?? 'Anggota Tim' }} · {{ $member->organizationalUnit?->name }}</p></div>
        <div class="heading-actions"><a class="button secondary" href="{{ route('reports.progress', ['period' => $period?->id, 'user' => $member->id]) }}">Laporan progres</a><form method="GET" class="filter-row"><label class="sr-only" for="period">Periode</label><select id="period" name="period">@foreach($periods as $item)<option value="{{ $item->id }}" @selected($period?->is($item))>{{ $item->period_start->translatedFormat('F Y') }}</option>@endforeach</select><button class="button secondary">Tampilkan</button></form></div>
    </section>

    @if(!$period || !$submission)
        <section class="empty-period"><h2>Belum ada data pada periode ini</h2><p>Pengguna belum membuat pengajuan kapasitas dan aktivitas.</p></section>
    @else
        <section class="detail-metrics" aria-label="Ringkasan perhitungan">
            <article><span>Kapasitas efektif</span><strong>{{ number_format($metrics['effective_minutes']/60, 1, ',', '.') }} jam</strong><small>{{ $submission->capacity?->work_days ?? 0 }} hari × {{ $submission->capacity?->hours_per_day ?? 0 }} jam × {{ $submission->capacity?->productive_percentage ?? 0 }}%</small></article>
            <article><span>Waktu aktual tercatat</span><strong>{{ number_format($metrics['required_minutes']/60, 1, ',', '.') }} jam</strong><small>{{ $submission->activities->count() }} catatan aktivitas</small></article>
            <article><span>Utilisasi</span><strong>{{ $metrics['utilization'] !== null ? number_format($metrics['utilization'], 1, ',', '.').'%' : 'Tidak dapat dihitung' }}</strong><small>{{ $metrics['status']->label() }}</small></article>
            <article><span>Status validasi</span><strong>{{ $submission->status->label() }}</strong><small>{{ $submission->reviewed_at?->translatedFormat('d M Y H:i') ?? 'Belum ditinjau' }}</small></article>
        </section>

        <section class="detail-grid">
            <article class="form-card">
                <div class="panel-heading"><div><h2>Catatan aktivitas aktual</h2><p>Waktu kerja diambil dari durasi yang dicatat pada setiap tanggal aktivitas.</p></div></div>
                <div class="source-list">@forelse($submission->activities as $activity)<div><span><strong>{{ $activity->name }}</strong><small>Aktivitas {{ $activity->activity_date?->translatedFormat('d M Y') ?? 'historis' }} · Dicatat {{ $activity->created_at->translatedFormat('d M Y H:i') }}</small><small><i class="timeliness-badge {{ $activity->timelinessClass() }}">{{ $activity->timelinessLabel() }}</i> · {{ $activity->category }} · {{ $activity->workTypeLabel() }}</small></span><span>{{ number_format($activity->monthly_volume, 1, ',', '.') }} {{ $activity->unit }}</span><b>{{ $activity->required_minutes }} menit</b></div>@empty<div class="empty-activities">Belum ada aktivitas aktual.</div>@endforelse</div>
            </article>
            <aside class="form-card">
                <div class="panel-heading"><div><h2>Riwayat validasi</h2><p>Jejak perubahan status pengajuan.</p></div></div>
                <div class="timeline-list">@forelse($submission->validationHistories->sortByDesc('created_at') as $history)<div><i></i><span><strong>{{ \App\Enums\SubmissionStatus::from($history->to_status)->label() }}</strong><small>{{ $history->actor?->name }} · {{ $history->created_at->translatedFormat('d M Y H:i') }}</small>@if($history->note)<p>{{ $history->note }}</p>@endif</span></div>@empty<div class="empty-activities">Belum ada riwayat validasi.</div>@endforelse</div>
            </aside>
        </section>

        <section class="form-card"><div class="panel-heading"><div><h2>Progres pekerjaan</h2><p>Data sumber laporan kerja mingguan dan bulanan.</p></div></div><div class="progress-list">@forelse($submission->progressItems as $progress)<article><div class="progress-list-main"><span class="progress-state {{ $progress->status->value }}">{{ $progress->status->label() }}</span><strong>{{ $progress->name }}</strong><small>{{ $progress->category }} · {{ $progress->report_date->translatedFormat('d M Y') }} · {{ $progress->progress_percentage }}%</small><p>{{ $progress->progress_summary }}</p>@if($progress->obstacle_note)<small><b>Kendala:</b> {{ $progress->obstacle_note }}</small>@endif<small><b>Langkah:</b> {{ $progress->action_note }}</small>@if($progress->start_date)<small><b>Rencana mulai:</b> {{ $progress->start_date->translatedFormat('d M Y') }}</small>@endif @if($progress->target_date)<small><b>Target selesai:</b> {{ $progress->target_date->translatedFormat('d M Y') }}</small>@endif</div></article>@empty<div class="empty-activities">Belum ada progres pekerjaan.</div>@endforelse</div></section>

        @if($submission->followUpActions->isNotEmpty())
        <section class="form-card"><div class="panel-heading"><div><h2>Tindak lanjut terkait</h2><p>Keputusan yang ditautkan ke hasil analisis ini.</p></div></div><div class="action-list">@foreach($submission->followUpActions as $action)<article><div class="action-state {{ $action->status }}"></div><div><h3>{{ $action->title }}</h3><p>{{ $action->description }}</p><span>{{ $action->owner?->name }} · {{ $action->target_date?->translatedFormat('d M Y') ?? 'Tanpa target' }}</span></div><span class="submission-state {{ $action->status === 'completed' ? 'approved' : 'draft' }}">{{ str($action->status)->replace('_', ' ')->title() }}</span></article>@endforeach</div></section>
        @endif
    @endif
</main>
</body>
</html>
