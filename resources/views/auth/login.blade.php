<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk — RuangKerja</title>@fonts @vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-brand-panel">
        <div class="brand light"><span class="brand-mark"><svg viewBox="0 0 24 24"><path d="M5 17.5V12m7 5.5V6m7 11.5V9"/></svg></span><div><strong>RuangKerja</strong><span>Workload intelligence</span></div></div>
        <div class="auth-message"><span class="eyebrow">KAPASITAS YANG LEBIH SEIMBANG</span><h1>Kerja terlihat.<br>Keputusan lebih adil.</h1><p>Satu ruang untuk mencatat kapasitas, memvalidasi aktivitas, dan mengambil tindakan yang dapat ditelusuri.</p></div>
        <p class="auth-principle">Utilisasi adalah alat diagnosis kapasitas, bukan nilai manusia.</p>
    </section>
    <section class="auth-form-panel">
        <form method="POST" action="{{ route('login.store') }}" class="auth-form">@csrf
            <div><p class="eyebrow">SELAMAT DATANG</p><h2>Masuk ke akun Anda</h2><p>Gunakan akun perusahaan yang telah terdaftar.</p></div>
            @if($errors->any())<div class="form-alert error">{{ $errors->first() }}</div>@endif
            <label class="field"><span>Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="username" placeholder="nama@perusahaan.com" required autofocus></label>
            <label class="field"><span>Kata sandi</span><input type="password" name="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required></label>
            <label class="checkbox-field"><input type="checkbox" name="remember" value="1"><span>Ingat saya di perangkat ini</span></label>
            <button class="button primary auth-submit" type="submit">Masuk ke RuangKerja</button>
            <p class="login-help">Mengalami kendala akses? Hubungi administrator internal.</p>
        </form>
    </section>
</main>
</body></html>
