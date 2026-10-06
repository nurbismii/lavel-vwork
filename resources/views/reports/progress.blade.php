<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Laporan Progres Kerja</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="progress-report-page">
<main class="progress-report-shell">
    <div class="progress-report-actions">
        <a href="{{ url()->previous() }}" class="button secondary">Kembali</a>
        <form method="GET" class="progress-report-filter">
            <label><span>Pegawai</span><select name="user">@foreach($visibleUsers as $user)<option value="{{ $user->id }}" @selected($member?->is($user))>{{ $user->name }}</option>@endforeach</select></label>
            <label><span>Periode</span><select name="period">@foreach($periods as $item)<option value="{{ $item->id }}" @selected($period?->is($item))>{{ $item->period_start->translatedFormat('F Y') }}</option>@endforeach</select></label>
            <label><span>Jenis laporan</span><select name="frequency"><option value="monthly" @selected($frequency === 'monthly')>Bulanan</option><option value="weekly" @selected($frequency === 'weekly')>Mingguan</option></select></label>
            <label><span>Awal minggu</span><input type="date" name="start_date" value="{{ $startDate->toDateString() }}" min="{{ $period?->period_start->toDateString() }}" max="{{ $period?->period_start->copy()->endOfMonth()->toDateString() }}"></label>
            <button class="button secondary" type="submit">Tampilkan</button>
        </form>
        <button class="button primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <header class="progress-report-header">
        <h1>LAPORAN KERJA</h1>
        <h2>DEPARTEMEN {{ strtoupper($member?->organizationalUnit?->name ?? '—') }}</h2>
    </header>

    <dl class="progress-report-identity">
        <div><dt>Nama</dt><dd>{{ $member?->name ?? '—' }}</dd></div>
        <div><dt>NIK</dt><dd>{{ $member?->employee_code ?? '—' }}</dd></div>
        <div><dt>Posisi</dt><dd>{{ $member?->position ?? '—' }}</dd></div>
        <div><dt>Periode</dt><dd>{{ $startDate->translatedFormat('d F Y') }} – {{ $endDate->translatedFormat('d F Y') }} ({{ $frequency === 'weekly' ? 'Mingguan' : 'Bulanan' }})</dd></div>
    </dl>

    @if($errors->any())<div class="form-alert error">{{ $errors->first() }}</div>@endif

    <table class="progress-report-table">
        <thead><tr><th>No.</th><th>Nama pekerjaan</th>@foreach($statuses as $status)<th>{{ strtoupper($status->label()) }}</th>@endforeach</tr></thead>
        <tbody>
        @php($rowNumber = 1)
        @forelse($jobs as $category => $categoryJobs)
            <tr class="category-row"><th colspan="5">{{ strtoupper($category) }}</th></tr>
            @foreach($categoryJobs as $job)
            <tr>
                <td class="number-cell">{{ $rowNumber++ }}.</td>
                <td class="job-cell">{{ $job['name'] }}</td>
                @foreach($statuses as $status)
                <td class="status-cell">
                    @forelse($job['by_status'][$status->value] as $item)
                        <section class="report-entry">
                            <p>{{ $item['progress_summary'] }}</p>
                            @if($item['actual_summary'])<small class="actual-recap">Rekap aktual: {{ $item['actual_summary'] }}</small>@endif
                            <small>Progres {{ $item['progress_percentage'] }}% · {{ $item['report_date']->translatedFormat('d M Y') }}@if($item['start_date']) · Rencana mulai {{ $item['start_date']->translatedFormat('d M Y') }}@endif @if($item['target_date']) · Target selesai {{ $item['target_date']->translatedFormat('d M Y') }}@endif</small>
                            @if($item['obstacle_note'])<div><strong>Kendala/Kronologi:</strong><p>{{ $item['obstacle_note'] }}</p></div>@endif
                            @if($item['action_note'])<div><strong>{{ $status === \App\Enums\ProgressStatus::Completed ? 'Langkah yang sudah diambil:' : 'Langkah yang akan diambil:' }}</strong><p>{{ $item['action_note'] }}</p></div>@endif
                        </section>
                    @empty
                        <span class="report-empty">—</span>
                    @endforelse
                </td>
                @endforeach
            </tr>
            @endforeach
        @empty
            <tr><td colspan="5" class="report-no-data">Belum ada progres pekerjaan pada rentang tanggal ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</main>
</body>
</html>
