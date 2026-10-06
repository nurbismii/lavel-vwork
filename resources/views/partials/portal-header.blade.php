<header class="portal-header admin-header">
    <a href="{{ route('dashboard') }}" class="portal-brand"><span class="brand-mark"><svg viewBox="0 0 24 24"><path d="M5 17.5V12m7 5.5V6m7 11.5V9"/></svg></span><strong>RuangKerja</strong></a>
    <nav class="portal-links" aria-label="Navigasi operasional">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <a href="{{ route('workload.entry') }}">Input saya</a>
        <a href="{{ route('follow-ups.mine') }}">Tindak lanjut saya</a>
        @if(auth()->user()->hasAnyRole(\App\Enums\UserRole::Manager,\App\Enums\UserRole::ProcessOwner,\App\Enums\UserRole::Administrator))
            <a href="{{ route('reviews.index') }}">Validasi</a><a href="{{ route('follow-ups.index') }}">Kelola tindak lanjut</a>
        @endif
        @if(auth()->user()->hasAnyRole(\App\Enums\UserRole::ProcessOwner,\App\Enums\UserRole::Administrator))
            <a href="{{ route('admin.periods.index') }}">Periode</a><a href="{{ route('admin.thresholds.index') }}">Ambang utilisasi</a><a href="{{ route('admin.activity-master-options.index') }}">Master aktivitas</a><a href="{{ route('admin.audit.index') }}">Audit</a>
        @endif
        @if(auth()->user()->role === \App\Enums\UserRole::Administrator)<a href="{{ route('admin.organization.index') }}">Organisasi</a>@endif
    </nav>
    <a href="{{ route('profile.edit') }}" class="profile-avatar-link" aria-label="Buka profil {{ auth()->user()->name }}" title="Profil saya"><span class="avatar avatar-indigo">{{ collect(explode(' ',auth()->user()->name))->map(fn($word)=>mb_substr($word,0,1))->take(2)->join('') }}</span></a>
</header>
