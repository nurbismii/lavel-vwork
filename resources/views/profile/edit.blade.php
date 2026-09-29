<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Kelola profil dan keamanan akun RuangKerja">
    <title>Profil Saya — RuangKerja</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@include('partials.portal-header')
<main class="form-page profile-page">
    <section class="form-page-heading">
        <div>
            <p class="eyebrow">AKUN SAYA</p>
            <h1>Profil dan keamanan</h1>
            <p>Perbarui identitas akun dan jaga kata sandi Anda tetap aman.</p>
        </div>
    </section>

    <section class="profile-overview" aria-label="Ringkasan akun">
        <span class="avatar avatar-indigo profile-avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn($word) => mb_substr($word, 0, 1))->take(2)->join('') }}</span>
        <div><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></div>
        <span class="submission-state approved">{{ auth()->user()->role?->label() ?? 'Pengguna' }}</span>
    </section>

    <div class="profile-grid">
        <section class="form-card">
            <div class="form-card-heading">
                <span>1</span>
                <div><h2>Informasi profil</h2><p>Nama dan email digunakan sebagai identitas Anda di aplikasi.</p></div>
            </div>

            @if(session('profile_success'))<div class="form-alert success">{{ session('profile_success') }}</div>@endif
            @if($errors->profileUpdate->any())
                <div class="form-alert error"><strong>Profil belum dapat disimpan.</strong><ul>@foreach($errors->profileUpdate->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}" class="compact-form">
                @csrf
                @method('PATCH')
                <label class="field"><span>Nama lengkap</span><input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" maxlength="255" autocomplete="name" required></label>
                <label class="field"><span>Email</span><input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" maxlength="255" autocomplete="email" required></label>
                <p class="profile-hint">Perubahan email dapat memerlukan verifikasi ulang jika verifikasi email diaktifkan.</p>
                <div class="profile-actions"><button class="button primary" type="submit">Simpan perubahan</button></div>
            </form>
        </section>

        <section class="form-card">
            <div class="form-card-heading">
                <span>2</span>
                <div><h2>Ganti kata sandi</h2><p>Gunakan minimal 10 karakter yang tidak digunakan di layanan lain.</p></div>
            </div>

            @if(session('password_success'))<div class="form-alert success">{{ session('password_success') }}</div>@endif
            @if($errors->passwordUpdate->any())
                <div class="form-alert error"><strong>Kata sandi belum dapat diubah.</strong><ul>@foreach($errors->passwordUpdate->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('profile.password.update') }}" class="compact-form">
                @csrf
                @method('PUT')
                <label class="field"><span>Kata sandi saat ini</span><input type="password" name="current_password" autocomplete="current-password" required></label>
                <label class="field"><span>Kata sandi baru</span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
                <label class="field"><span>Konfirmasi kata sandi baru</span><input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
                <div class="profile-actions"><button class="button primary" type="submit">Perbarui kata sandi</button></div>
            </form>
        </section>
    </div>

    <section class="profile-signout">
        <div><strong>Keluar dari akun</strong><p>Akhiri sesi pada perangkat ini setelah selesai menggunakan aplikasi.</p></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="button secondary" type="submit">Keluar</button></form>
    </section>
</main>
</body>
</html>
