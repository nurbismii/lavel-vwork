<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Dashboard pengukuran beban kerja tim bulanan">
    <title>RuangKerja — Dashboard Tim</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a href="#main-content" class="skip-link">Lewati ke konten utama</a>
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 17.5V12m7 5.5V6m7 11.5V9"/></svg></span>
            <div><strong>RuangKerja</strong><span>Workload intelligence</span></div>
            <button class="icon-button sidebar-close" type="button" aria-label="Tutup menu" data-sidebar-close><svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <nav class="main-nav">
            <p class="nav-label">Ruang kerja</p>
            <a href="{{ route('dashboard') }}" class="nav-item active" aria-current="page"><svg viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm0 7h6v-3H4v3Zm10 0h6v-9h-6v9Zm0-13h6V4h-6v3Z"/></svg><span>Dashboard</span></a>
            <a href="{{ route('workload.entry') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M9 11h6m-6 4h6M7 3h8l4 4v14H5V3h2Zm8 0v5h4"/></svg><span>Input saya</span></a>
            <a href="{{ route('follow-ups.mine') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="m9 11 3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Tindak lanjut saya</span>@if($myOpenFollowUps)<span class="nav-count">{{ $myOpenFollowUps }}</span>@endif</a>
            <a href="#team-members" class="nav-item"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-2-12a4 4 0 0 1 0 7.75"/></svg><span>Anggota tim</span><span class="nav-count">{{ $members->count() }}</span></a>
            <a href="#insights" class="nav-item"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h16M8 16v-4m4 4V7m4 9v-6"/></svg><span>Analitik</span></a>
            <a href="{{ route('reports.print') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M9 11h6m-6 4h6M7 3h8l4 4v14H5V3h2Zm8 0v5h4"/></svg><span>Laporan</span></a>
            @if(auth()->user()->hasAnyRole(\App\Enums\UserRole::Manager, \App\Enums\UserRole::ProcessOwner, \App\Enums\UserRole::Administrator))
                <p class="nav-label nav-label-spaced">Kelola</p>
                <a href="{{ route('reviews.index') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg><span>Validasi</span></a>
                <a href="{{ route('follow-ups.index') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="m9 11 3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Kelola tindak lanjut</span></a>
            @endif
            @if(auth()->user()->hasAnyRole(\App\Enums\UserRole::ProcessOwner, \App\Enums\UserRole::Administrator))
                <a href="{{ route('admin.periods.index') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg><span>Periode</span></a>
                <a href="{{ route('admin.thresholds.index') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h16M8 16v-4m4 4V7m4 9v-6"/></svg><span>Ambang utilisasi</span></a>
                <a href="{{ route('admin.activity-master-options.index') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10M7 3v6m6 0v6m6-6v6"/></svg><span>Master aktivitas</span></a>
                <a href="{{ route('admin.audit.index') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M9 11h6m-6 4h6M7 3h8l4 4v14H5V3h2Zm8 0v5h4"/></svg><span>Audit</span></a>
            @endif
            @if(auth()->user()->role === \App\Enums\UserRole::Administrator)
                <a href="{{ route('admin.organization.index') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-2-12a4 4 0 0 1 0 7.75"/></svg><span>Organisasi</span></a>
            @endif
            <a href="#data-guidance" class="nav-item"><svg viewBox="0 0 24 24"><path d="M9 12h6m-3-3v6m9-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg><span>Panduan data</span></a>
        </nav>
        <div class="sidebar-note"><span class="note-icon">i</span><div><strong>Tentang data ini</strong><p>Utilisasi membantu membaca kapasitas, bukan menilai kinerja individu.</p></div></div>
        <a href="{{ route('profile.edit') }}" class="profile-card" title="Buka profil"><span class="avatar avatar-indigo">{{ collect(explode(' ', auth()->user()->name))->map(fn($word) => mb_substr($word, 0, 1))->take(2)->join('') }}</span><span class="profile-copy"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->position ?? auth()->user()->role->label() }}</span></span><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="content-shell">
        <header class="topbar">
            <button class="icon-button menu-button" type="button" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false" data-sidebar-open><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            <div class="topbar-context"><span>Dashboard tim</span><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg><strong>Operasional</strong></div>
            <div class="topbar-actions"><button class="icon-button notification-button" type="button" aria-label="Notifikasi, {{ $summary['submitted_count'] }} belum dibaca"><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/></svg>@if($summary['submitted_count'])<span></span>@endif</button><i></i><a href="{{ route('profile.edit') }}" class="profile-avatar-link" aria-label="Buka profil {{ auth()->user()->name }}" title="Profil saya"><span class="avatar avatar-indigo small">{{ collect(explode(' ', auth()->user()->name))->map(fn($word) => mb_substr($word, 0, 1))->take(2)->join('') }}</span></a></div>
        </header>

        <main id="main-content" tabindex="-1">
            <section class="page-heading">
                <div><p class="eyebrow">RINGKASAN BULANAN</p><h1>Selamat datang, {{ str(auth()->user()->name)->before(' ') }}</h1><p>Lihat kondisi kapasitas tim dan hal yang perlu ditindaklanjuti.</p></div>
                <div class="heading-actions">
                    <a class="button secondary" href="{{ route('reports.xlsx', request()->only(['period','unit','status'])) }}"><svg viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg>Ekspor XLSX</a>
                    @if(auth()->user()->hasAnyRole(\App\Enums\UserRole::Manager, \App\Enums\UserRole::ProcessOwner, \App\Enums\UserRole::Administrator))<a href="{{ route('reviews.index') }}" class="button primary">Tinjau pengajuan <span>{{ $summary['submitted_count'] }}</span></a>@endif
                </div>
            </section>

            @if($period)
            <form method="GET" class="dashboard-filters" aria-label="Filter dashboard">
                <label><span>Periode</span><select name="period">@foreach($periods as $item)<option value="{{ $item->id }}" @selected($period->is($item))>{{ $item->period_start->translatedFormat('F Y') }}</option>@endforeach</select></label>
                <label><span>Unit</span><select name="unit"><option value="">Semua unit dalam cakupan</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(request('unit')==$unit->id)>{{ $unit->name }}</option>@endforeach</select></label>
                <label><span>Status beban</span><select name="status"><option value="">Semua status</option>@foreach(\App\Enums\WorkloadStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>@endforeach</select></label>
                <button class="button primary" type="submit">Terapkan</button>
                @if(request()->hasAny(['unit','status']))<a class="text-button" href="{{ route('dashboard', ['period'=>$period->id]) }}">Reset</a>@endif
            </form>
            <section class="period-panel" aria-labelledby="period-title">
                <div class="period-copy"><span class="period-icon"><svg viewBox="0 0 24 24"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg></span><div><p id="period-title">Periode aktif</p><strong>{{ $period->period_start->translatedFormat('F Y') }}</strong></div><span class="status-pill"><i></i>{{ $period->isOpen() ? 'Terbuka' : 'Terkunci' }}</span></div>
                <div class="deadline"><div><span>Batas pengisian</span><strong>{{ $period->submission_deadline->translatedFormat('d F Y') }}</strong></div><b>{{ max(0, now()->startOfDay()->diffInDays($period->submission_deadline, false)) }} hari lagi</b></div>
                <a href="{{ route('workload.entry') }}" class="button secondary">Catat aktivitas</a>
            </section>
            @else
            <section class="period-panel"><div class="period-copy"><div><p>Belum ada periode</p><strong>Hubungi PIC untuk membuka periode bulanan.</strong></div></div></section>
            @endif

            <section class="kpi-grid" aria-label="Indikator utama tim">
                <article class="kpi-card"><div class="kpi-top"><span>Kapasitas efektif</span><span class="kpi-icon blue"><svg viewBox="0 0 24 24"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg></span></div><div class="kpi-value">{{ number_format($summary['effective_minutes']/60, 1, ',', '.') }} <small>jam</small></div><p>Hanya data yang disetujui</p></article>
                <article class="kpi-card"><div class="kpi-top"><span>Waktu aktual tercatat</span><span class="kpi-icon violet"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h16M8 16v-4m4 4V7m4 9v-6"/></svg></span></div><div class="kpi-value">{{ number_format($summary['required_minutes']/60, 1, ',', '.') }} <small>jam</small></div><p>Akumulasi catatan aktivitas aktual</p></article>
                <article class="kpi-card emphasized"><div class="kpi-top"><span>Utilisasi tim</span><span class="kpi-icon teal"><svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 1-9 9h9V3Zm4 1.3A9 9 0 0 1 20.7 9H16V4.3Z"/></svg></span></div><div class="kpi-value">{{ $summary['utilization'] !== null ? number_format($summary['utilization'],1,',','.') : '—' }}<small>%</small></div><div class="meter"><span style="width:{{ min($summary['utilization'] ?? 0,100) }}%"></span></div><p>Ambang {{ $summary['threshold']?->is_provisional ? 'masih provisional' : 'telah dikalibrasi' }}</p></article>
                <article class="kpi-card"><div class="kpi-top"><span>Data disetujui</span><span class="kpi-icon green"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span></div><div class="kpi-value">{{ $summary['approved_count'] }} <small>dari {{ $members->count() }}</small></div><p><b class="warning">{{ $summary['submitted_count'] }} pengajuan</b> perlu ditinjau</p></article>
            </section>

            <section class="insight-grid" id="insights">
                <article class="panel workload-panel">
                    <div class="panel-heading"><div><h2>Distribusi beban tim</h2><p>{{ $summary['approved_count'] }} anggota dengan data disetujui</p></div><button class="text-button" type="button" data-scroll-team>Lihat detail <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button></div>
                    <div class="distribution-layout">
                        @php
                            $chartTotal = max($summary['approved_count'], 1);
                            $chartAvailable = $summary['distribution']->get('available', 0) / $chartTotal * 100;
                            $chartHealthy = $chartAvailable + $summary['distribution']->get('healthy', 0) / $chartTotal * 100;
                            $chartDense = $chartHealthy + $summary['distribution']->get('dense', 0) / $chartTotal * 100;
                        @endphp
                        <div class="donut" style="background:conic-gradient(#4386c5 0 {{ $chartAvailable }}%,#3a9572 {{ $chartAvailable }}% {{ $chartHealthy }}%,#dda343 {{ $chartHealthy }}% {{ $chartDense }}%,#d45d5d {{ $chartDense }}% 100%)" role="img" aria-label="Distribusi status beban anggota"><div><strong>{{ $summary['approved_count'] }}</strong><span>disetujui</span></div></div>
                        <div class="legend-list">
                            @foreach(['available'=>'Kapasitas tersedia','healthy'=>'Sehat','dense'=>'Padat','overload'=>'Kelebihan beban'] as $key=>$label)
                            @php($count = $summary['distribution']->get($key, 0))
                            <a href="{{ route('dashboard', [...request()->except('status'), 'status'=>$key]) }}"><i class="{{ $key }}"></i><span>{{ $label }}</span><strong>{{ $count }}</strong><small>{{ $summary['approved_count'] ? number_format($count/$summary['approved_count']*100,1,',','.') : 0 }}%</small></a>
                            @endforeach
                        </div>
                    </div>
                    <div class="context-note"><svg viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg><p><strong>Baca bersama konteks.</strong> Utilisasi tinggi perlu divalidasi dengan prioritas, kualitas data, dan hambatan proses.</p></div>
                </article>
                <article class="panel attention-panel">
                    <div class="panel-heading"><div><h2>Perlu perhatian</h2><p>Prioritas tindak lanjut Anda</p></div><span class="attention-count">{{ $summary['distribution']->get('overload',0) + $summary['submitted_count'] + $dueFollowUps }}</span></div>
                    <div class="attention-list">
                        <a href="{{ route('dashboard', [...request()->except('status'), 'status'=>'overload']) }}"><span class="attention-icon red"><svg viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 4.1 2.6 18h18.8L13.7 4.1a2 2 0 0 0-3.4 0Z"/></svg></span><span><strong>{{ $summary['distribution']->get('overload',0) }} anggota kelebihan beban</strong><small>Validasi data dan tentukan tindakan kapasitas</small></span><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
                        @if(auth()->user()->hasAnyRole(\App\Enums\UserRole::Manager, \App\Enums\UserRole::ProcessOwner, \App\Enums\UserRole::Administrator))<a href="{{ route('reviews.index') }}"><span class="attention-icon amber"><svg viewBox="0 0 24 24"><path d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span><span><strong>{{ $summary['submitted_count'] }} pengajuan menunggu</strong><small>Tinjau sebelum batas pengisian</small></span><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>@endif
                        <a href="{{ route('dashboard', [...request()->except('status'), 'status'=>'available']) }}"><span class="attention-icon blue"><svg viewBox="0 0 24 24"><path d="M12 20V10m0 10-4-4m4 4 4-4M5 4h14"/></svg></span><span><strong>{{ $summary['distribution']->get('available',0) }} anggota punya kapasitas</strong><small>Pertimbangkan redistribusi pekerjaan</small></span><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
                        @if(auth()->user()->hasAnyRole(\App\Enums\UserRole::Manager, \App\Enums\UserRole::ProcessOwner, \App\Enums\UserRole::Administrator))<a href="{{ route('follow-ups.index') }}"><span class="attention-icon violet"><svg viewBox="0 0 24 24"><path d="m9 11 3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span><strong>{{ $dueFollowUps }} tindak lanjut jatuh tempo</strong><small>Buka daftar tindakan untuk memperbarui progres</small></span><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>@endif
                    </div>
                </article>
            </section>

            <section class="panel team-panel" id="team-members">
                <div class="panel-heading team-heading"><div><h2>Kondisi anggota tim</h2><p>Klik baris untuk melihat sumber perhitungan dan riwayat validasi.</p></div><div class="table-tools"><label class="search-control"><span class="sr-only">Cari anggota</span><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" placeholder="Cari anggota pada hasil ini" data-search></label></div></div>
                <div class="table-wrap"><table><thead><tr><th>Anggota</th><th>Kapasitas</th><th>Waktu aktual</th><th>Utilisasi</th><th>Status beban</th><th>Validasi</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody>
                    @foreach($members as $item)
                    @php($member = $item['user']) @php($metrics = $item['metrics']) @php($submission = $item['submission'])
                    <tr tabindex="0" data-member data-detail-url="{{ route('members.show', ['user' => $member, 'period' => $period->id]) }}" data-status="{{ $metrics['status']->value }}" data-name="{{ strtolower($member->name) }}">
                        <td><span class="person"><span class="avatar avatar-{{ ['coral','blue','violet','green','teal','gold','pink','sky'][$loop->index % 8] }}">{{ collect(explode(' ', $member->name))->map(fn($word) => mb_substr($word,0,1))->take(2)->join('') }}</span><span><strong>{{ $member->name }}</strong><small>{{ $member->position ?? 'Anggota Tim' }}</small></span></span></td><td>{{ number_format($metrics['effective_minutes']/60,1,',','.') }} jam</td><td>{{ number_format($metrics['required_minutes']/60,1,',','.') }} jam</td>
                        <td><strong>{{ $metrics['utilization'] !== null ? number_format($metrics['utilization'],1,',','.') .'%' : '—' }}</strong><span class="mini-meter {{ $metrics['status']->value }}"><i style="width:{{ min($metrics['utilization'] ?? 0,100) }}%"></i></span></td>
                        <td><span class="status-badge {{ $metrics['status']->value }}"><i></i>{{ $metrics['status']->label() }}</span></td><td><span class="validation {{ match($submission?->status?->value){'approved'=>'approved','submitted'=>'pending','revision_required'=>'revision',default=>'draft'} }}">{{ $submission?->status?->label() ?? 'Belum mengisi' }}</span></td>
                        <td><a href="{{ route('members.show', ['user' => $member, 'period' => $period->id]) }}" class="row-action" aria-label="Lihat detail {{ $member->name }}"><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a></td>
                    </tr>
                    @endforeach
                </tbody></table><div class="empty-state" hidden data-empty-state><strong>Tidak ada anggota ditemukan</strong><p>Coba gunakan kata kunci atau status lain.</p></div></div>
                <div class="table-footer"><span data-result-count>Menampilkan {{ $members->count() }} dari {{ $members->count() }} anggota</span><a class="text-button" href="{{ route('reports.print', request()->only(['period','unit','status'])) }}">Buka laporan lengkap <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a></div>
            </section>
            <p class="data-disclaimer" id="data-guidance"><svg viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>Hanya data berstatus Disetujui yang masuk agregasi. Ambang utilisasi {{ $summary['threshold']?->available_below ?? 70 }}% / {{ $summary['threshold']?->healthy_up_to ?? 85 }}% / {{ $summary['threshold']?->dense_up_to ?? 100 }}% {{ $summary['threshold']?->is_provisional ? 'masih provisional' : 'telah dikalibrasi' }}.</p>
        </main>
    </div>
</div>
<div class="toast" role="status" aria-live="polite" data-toast><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg><span></span></div>
</body>
</html>
