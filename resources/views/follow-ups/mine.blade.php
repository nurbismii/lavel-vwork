<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Daftar tindak lanjut yang menjadi tanggung jawab saya">
    <title>Tindak Lanjut Saya — RuangKerja</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@include('partials.portal-header')
<main class="form-page admin-page">
    <section class="form-page-heading">
        <div>
            <p class="eyebrow">TUGAS SAYA</p>
            <h1>Tindak lanjut saya</h1>
            <p>Pantau tindakan yang ditugaskan kepada Anda dan perbarui status pengerjaannya.</p>
        </div>
    </section>

    @include('partials.feedback')

    <section class="kpi-grid" aria-label="Ringkasan tindak lanjut saya">
        <article class="kpi-card"><div class="kpi-top"><span>Perlu dikerjakan</span></div><div class="kpi-value">{{ $openCount }} <small>tindakan</small></div><p>Status terbuka atau sedang berjalan.</p></article>
        <article class="kpi-card emphasized"><div class="kpi-top"><span>Jatuh tempo</span></div><div class="kpi-value">{{ $dueCount }} <small>tindakan</small></div><p>Target hari ini atau telah melewati target.</p></article>
        <article class="kpi-card"><div class="kpi-top"><span>Selesai</span></div><div class="kpi-value">{{ $completedCount }} <small>tindakan</small></div><p>Tindakan yang telah Anda selesaikan.</p></article>
    </section>

    <section class="form-card">
        <div class="panel-heading">
            <div><h2>Daftar tugas</h2><p>{{ $actions->count() }} tindakan sesuai filter.</p></div>
            <form method="GET" class="filter-row">
                <label class="sr-only" for="my-follow-up-status">Filter status</label>
                <select id="my-follow-up-status" name="status">
                    <option value="">Semua status</option>
                    @foreach(['open' => 'Terbuka', 'in_progress' => 'Berjalan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="button secondary" type="submit">Filter</button>
            </form>
        </div>

        <div class="action-list">
            @forelse($actions as $action)
                @php
                    $statusLabel = match($action->status) {
                        'open' => 'Terbuka',
                        'in_progress' => 'Berjalan',
                        'completed' => 'Selesai',
                        default => 'Dibatalkan',
                    };
                    $typeLabel = match($action->action_type) {
                        'redistribution' => 'Redistribusi',
                        'process_improvement' => 'Perbaikan proses',
                        'automation' => 'Otomatisasi',
                        default => 'Kajian kebutuhan tenaga',
                    };
                    $isDue = $action->target_date?->lte(today()) && in_array($action->status, ['open', 'in_progress'], true);
                @endphp
                <article>
                    <div class="action-state {{ $action->status }}" aria-hidden="true"></div>
                    <div>
                        <h3>{{ $action->title }}</h3>
                        <p>{{ $action->description ?: 'Tidak ada deskripsi.' }}</p>
                        <span>
                            {{ $typeLabel }} · {{ $action->organizationalUnit?->name }} · {{ $action->target_date?->translatedFormat('d M Y') ?? 'Tanpa target' }}
                            @if($isDue) · Jatuh tempo @endif
                            @if($action->submission) · Terkait pengajuan {{ $action->submission->user->name }} ({{ $action->submission->workPeriod->period_start->format('m/Y') }}) @endif
                        </span>
                    </div>
                    <span class="submission-state {{ $action->status === 'completed' ? 'approved' : ($action->status === 'in_progress' ? 'submitted' : 'draft') }}">{{ $statusLabel }}</span>
                    @if($action->status !== 'cancelled')
                        <details>
                            <summary>Perbarui status</summary>
                            <form method="POST" action="{{ route('follow-ups.mine.update', $action) }}" class="compact-form">
                                @csrf
                                @method('PATCH')
                                <label class="field"><span>Status</span><select name="status" required>
                                    @foreach(['open' => 'Terbuka', 'in_progress' => 'Berjalan', 'completed' => 'Selesai'] as $key => $label)
                                        <option value="{{ $key }}" @selected($action->status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select></label>
                                <button class="button secondary" type="submit">Simpan status</button>
                            </form>
                        </details>
                    @endif
                </article>
            @empty
                <div class="empty-activities">{{ request('status') ? 'Tidak ada tindak lanjut dengan status tersebut.' : 'Belum ada tindak lanjut yang ditugaskan kepada Anda.' }}</div>
            @endforelse
        </div>
    </section>
</main>
</body>
</html>
