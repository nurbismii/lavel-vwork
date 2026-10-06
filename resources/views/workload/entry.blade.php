<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Laporan Kerja Saya — RuangKerja</title>@fonts @vite(['resources/css/app.css','resources/js/app.js'])</head>
<body data-clear-draft="{{ session('clear_draft') }}">
<header class="portal-header"><a href="{{ route('dashboard') }}" class="portal-brand"><span class="brand-mark"><svg viewBox="0 0 24 24"><path d="M5 17.5V12m7 5.5V6m7 11.5V9"/></svg></span><strong>RuangKerja</strong></a><div><a href="{{ route('dashboard') }}" class="button secondary">← Kembali ke dashboard</a><span class="avatar avatar-indigo">{{ collect(explode(' ',auth()->user()->name))->map(fn($w)=>mb_substr($w,0,1))->take(2)->join('') }}</span></div></header>
<main class="form-page">
    <section class="form-page-heading"><div><p class="eyebrow">JURNAL AKTIVITAS DAN PROGRES</p><h1>Laporan kerja saya</h1><p>Catat aktivitas aktual untuk perhitungan beban kerja dan progres pekerjaan untuk laporan mingguan atau bulanan.</p></div><div class="heading-actions">@if($period)<a class="button secondary" href="{{ route('reports.progress', ['period' => $period->id, 'user' => auth()->id()]) }}">Lihat laporan progres</a>@endif @if($submission)<span class="submission-state {{ $submission->status->value }}">{{ $submission->status->label() }}</span>@endif</div></section>
    @if(session('success'))<div class="form-alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="form-alert error"><strong>Data belum dapat disimpan.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(!$period)<div class="empty-period"><h2>Belum ada periode terbuka</h2><p>Input tersedia setelah PIC membuka periode bulanan.</p></div>@else
    <section class="entry-status"><div><span>Periode</span><strong>{{ $period->period_start->translatedFormat('F Y') }}</strong></div><div><span>Batas pengisian</span><strong>{{ $period->submission_deadline->translatedFormat('d F Y') }}</strong></div><div><span>Kelengkapan</span><strong>{{ $submission->capacity && $submission->activities->isNotEmpty() ? 'Siap diajukan' : 'Belum lengkap' }}</strong></div></section>
    @php($activeTab = old('entry_mode') || session('entry_tab') === 'progress' || request('tab') === 'progress' ? 'progress' : 'activity')
    <div class="entry-layout">
        <div class="entry-main">
            <details class="form-card entry-capacity">
                <summary><span><strong>Acuan kapasitas</strong><small>Diterapkan otomatis sesuai standar periode</small></span><b>{{ number_format($metrics['effective_minutes']/60,1,',','.') }} jam</b></summary>
                @if($submission->capacity)
                    <section class="capacity-standard-card" aria-label="Kapasitas standar periode">
                        <header>
                            <div class="capacity-standard-title"><span class="capacity-standard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg></span><div><span>Kapasitas efektif periode</span><strong>{{ $period->period_start->translatedFormat('F Y') }}</strong></div></div>
                            <span class="capacity-policy-badge"><i></i>{{ $submission->capacity->cycle_work_days ? 'Pola '.$submission->capacity->cycle_work_days.':'.$submission->capacity->cycle_off_days : 'Standar lama' }}</span>
                        </header>
                        <div class="capacity-standard-body">
                            <div class="capacity-effective-result"><span>Total kapasitas efektif</span><strong>{{ number_format($submission->capacity->effective_minutes/60,1,',','.') }} <small>jam</small></strong><p>Diterapkan otomatis pada perhitungan utilisasi Anda.</p></div>
                            <div class="capacity-equation" aria-label="Rumus kapasitas efektif">
                                <div><span>Hari kerja</span><strong>{{ $submission->capacity->work_days }} <small>hari</small></strong></div><b aria-hidden="true">×</b>
                                <div><span>Jam per hari</span><strong>{{ number_format($submission->capacity->hours_per_day,2,',','.') }} <small>jam</small></strong></div><b aria-hidden="true">×</b>
                                <div><span>Waktu produktif</span><strong>{{ number_format($submission->capacity->productive_percentage,1,',','.') }}<small>%</small></strong></div>
                            </div>
                        </div>
                        <footer><span>i</span><p>{{ $period->capacity_policy_note ?: 'Cuti dan libur nasional tidak mengurangi kapasitas dasar. Pola dan jam kerja mengikuti profil anggota.' }}</p></footer>
                    </section>
                @endif
            </details>

            <nav class="entry-tabs" aria-label="Bagian laporan kerja">
                <a href="{{ route('workload.entry') }}" @if($activeTab === 'activity') aria-current="page" @endif>Aktivitas aktual <span>{{ $submission->activities->count() }}</span></a>
                <a href="{{ route('workload.entry', ['tab' => 'progress']) }}" @if($activeTab === 'progress') aria-current="page" @endif>Progres pekerjaan <span>{{ $submission->progressItems->count() }}</span></a>
            </nav>

            @if($activeTab === 'activity')
            <section class="form-card">
                <div class="form-card-heading"><div><h2>Catat aktivitas aktual</h2><p>Masukkan satu catatan untuk pekerjaan yang dilakukan pada hari tersebut. Hindari pencatatan aktivitas yang sama dua kali.</p></div></div>
                <form method="POST" action="{{ route('workload.activity') }}" class="activity-form" data-draft-form data-draft-key="activity-{{ $submission->id }}">@csrf
                    <label class="field"><span>Tanggal penginputan <em>read-only</em></span><input type="text" value="{{ today()->translatedFormat('d F Y') }}" readonly aria-readonly="true"></label>
                    <label class="field"><span>Tanggal aktivitas</span><input type="date" name="activity_date" min="{{ $period->period_start->toDateString() }}" max="{{ min(today(), $period->period_start->copy()->endOfMonth())->toDateString() }}" value="{{ old('activity_date', today()->betweenIncluded($period->period_start, $period->period_start->copy()->endOfMonth()) ? today()->toDateString() : $period->period_start->copy()->endOfMonth()->toDateString()) }}" required></label>
                    <label class="field span-2"><span>Nama aktivitas</span><input name="name" value="{{ old('name') }}" placeholder="Contoh: Meninjau laporan operasional" maxlength="255" required></label>
                    <label class="field"><span>Kategori</span><div class="editable-select"><input name="category" list="category-options" value="{{ old('category') }}" placeholder="Pilih atau ketik kategori baru" maxlength="100" autocomplete="off" required><i aria-hidden="true"></i></div><small>Pilih dari master atau ketik pilihan baru.</small></label>
                    <label class="field"><span>Sifat pekerjaan</span><div class="editable-select"><input name="work_type" list="work-type-options" value="{{ old('work_type') }}" placeholder="Pilih atau ketik sifat baru" maxlength="100" autocomplete="off" required><i aria-hidden="true"></i></div><small>Opsi baru otomatis disimpan ke master.</small></label>
                    <div class="field">
                        <div class="field-label-row">
                            <label for="actual_minutes">Durasi aktual</label>
                            <span class="field-help-wrap">
                                <details class="field-help">
                                    <summary aria-label="Penjelasan durasi aktual">?</summary>
                                </details>
                                <span class="field-help-content" role="tooltip">Total waktu yang benar-benar digunakan untuk aktivitas ini pada tanggal tersebut, dalam menit.</span>
                            </span>
                        </div>
                        <input id="actual_minutes" type="number" name="actual_minutes" min="1" max="1440" step="1" value="{{ old('actual_minutes') }}" placeholder="Contoh: 45" required>
                    </div>
                    <div class="field">
                        <div class="field-label-row">
                            <label for="actual_volume">Hasil/volume aktual <em>opsional</em></label>
                            <span class="field-help-wrap">
                                <details class="field-help">
                                    <summary aria-label="Penjelasan hasil atau volume aktual">?</summary>
                                </details>
                                <span class="field-help-content" role="tooltip">Jumlah hasil yang benar-benar diselesaikan pada tanggal tersebut. Kosongkan jika aktivitas tidak memiliki hasil yang dapat dihitung.</span>
                            </span>
                        </div>
                        <input id="actual_volume" type="number" name="actual_volume" min="0.01" step="0.01" value="{{ old('actual_volume') }}" placeholder="Contoh: 3">
                    </div>
                    <div class="field">
                        <div class="field-label-row">
                            <label for="unit">Satuan <em>opsional</em></label>
                            <span class="field-help-wrap">
                                <details class="field-help">
                                    <summary aria-label="Penjelasan satuan">?</summary>
                                </details>
                                <span class="field-help-content" role="tooltip">Jenis hasil aktual, misalnya dokumen, transaksi, laporan, atau pertemuan. Wajib jika volume aktual diisi.</span>
                            </span>
                        </div>
                        <input id="unit" name="unit" value="{{ old('unit') }}" placeholder="Contoh: Dokumen" maxlength="50">
                    </div>
                    <label class="field"><span>Keterangan <em>opsional</em></span><input name="exception_reason" value="{{ old('exception_reason') }}" placeholder="Hasil, kendala, atau konteks penting" maxlength="1000"></label>
                    <div class="form-action span-2"><button class="button primary" type="submit" @disabled(!$submission->isEditable())>+ Catat aktivitas aktual</button></div>
                    <small class="draft-indicator span-2" data-draft-indicator aria-live="polite">Draf catatan akan tersimpan otomatis di perangkat ini.</small>
                    <datalist id="work-type-options">@foreach($workTypeOptions as $option)<option value="{{ $option->label }}"></option>@endforeach</datalist>
                </form>
                <div class="activity-list">
                    @forelse($submission->activities as $activity)
                    <article><div><strong>{{ $activity->name }}</strong><span>Aktivitas {{ $activity->activity_date?->translatedFormat('d M Y') ?? 'historis' }} · Dicatat {{ $activity->created_at->translatedFormat('d M Y H:i') }}</span><span><i class="timeliness-badge {{ $activity->timelinessClass() }}">{{ $activity->timelinessLabel() }}</i> · {{ $activity->category }} · {{ $activity->workTypeLabel() }}</span>@if($activity->exception_reason)<small>{{ $activity->exception_reason }}</small>@endif</div><div><span>{{ number_format($activity->monthly_volume,2,',','.') }} {{ $activity->unit }}</span><strong>{{ $activity->required_minutes }} menit</strong></div>@if($submission->isEditable())<form method="POST" action="{{ route('workload.activity.destroy',$activity) }}">@csrf @method('DELETE')<button type="submit" aria-label="Hapus {{ $activity->name }}">×</button></form>@endif</article>
                    @empty<div class="empty-activities">Belum ada aktivitas aktual. Catat pekerjaan yang telah dilakukan hari ini.</div>@endforelse
                </div>
            </section>

            @else
            <section class="form-card">
                <div class="form-card-heading"><div><h2>Lengkapi laporan progres</h2><p>Pilih pekerjaan dari aktivitas aktual. Nama pekerjaan, kategori, jumlah catatan, dan total waktunya akan dirangkum otomatis.</p></div></div>
                @if($activityGroups->isNotEmpty())
                <form method="POST" action="{{ route('workload.progress.store') }}" class="activity-form" data-draft-form data-draft-key="progress-actual-{{ $submission->id }}">@csrf
                    <input type="hidden" name="entry_mode" value="actual">
                    <label class="field"><span>Tanggal laporan</span><input type="date" name="report_date" min="{{ $period->period_start->toDateString() }}" max="{{ min(today(), $period->period_start->copy()->endOfMonth())->toDateString() }}" value="{{ old('entry_mode') !== 'planned' ? old('report_date', today()->betweenIncluded($period->period_start, $period->period_start->copy()->endOfMonth()) ? today()->toDateString() : $period->period_start->copy()->endOfMonth()->toDateString()) : (today()->betweenIncluded($period->period_start, $period->period_start->copy()->endOfMonth()) ? today()->toDateString() : $period->period_start->copy()->endOfMonth()->toDateString()) }}" required></label>
                    <label class="field"><span>Status pekerjaan</span><select name="status" required><option value="">Pilih status</option><option value="completed" @selected(old('entry_mode') !== 'planned' && old('status') === 'completed')>Selesai dikerjakan</option><option value="in_progress" @selected(old('entry_mode') !== 'planned' && old('status') === 'in_progress')>Sedang dikerjakan</option></select></label>
                    <label class="field span-2"><span>Pekerjaan dari aktivitas aktual</span><select name="source_activity_id" required><option value="">Pilih pekerjaan</option>@foreach($activityGroups as $group)<option value="{{ $group['activity_id'] }}" @selected(old('entry_mode') !== 'planned' && (int) old('source_activity_id') === $group['activity_id'])>{{ $group['category'] }} — {{ $group['name'] }} ({{ $group['count'] }} catatan, {{ number_format($group['minutes']/60, 1, ',', '.') }} jam)</option>@endforeach</select><small>Angka dihitung dari seluruh aktivitas dengan kategori dan nama yang sama.</small></label>
                    <label class="field span-2"><span>Ringkasan hasil</span><textarea name="progress_summary" rows="3" maxlength="3000" placeholder="Jelaskan hasil utama yang perlu dibaca pada laporan." required>{{ old('entry_mode') !== 'planned' ? old('progress_summary') : '' }}</textarea></label>
                    <label class="field span-2"><span>Kendala atau kronologi <em>opsional</em></span><textarea name="obstacle_note" rows="3" maxlength="3000" placeholder="Jelaskan kendala, penyebab, dan konteks yang perlu diketahui.">{{ old('entry_mode') !== 'planned' ? old('obstacle_note') : '' }}</textarea></label>
                    <label class="field span-2"><span>Langkah yang sudah atau akan diambil</span><textarea name="action_note" rows="3" maxlength="3000" placeholder="Tuliskan tindakan penyelesaian atau langkah berikutnya." required>{{ old('entry_mode') !== 'planned' ? old('action_note') : '' }}</textarea></label>
                    <label class="field"><span>Progres</span><input type="number" name="progress_percentage" min="1" max="100" step="1" value="{{ old('entry_mode') !== 'planned' ? old('progress_percentage', 100) : 100 }}" required><small>Selesai: 100%; sedang dikerjakan: 1–99%.</small></label>
                    <label class="field"><span>Target selesai</span><input type="date" name="target_date" value="{{ old('entry_mode') !== 'planned' ? old('target_date') : '' }}"><small>Wajib jika pekerjaan masih berlangsung.</small></label>
                    <div class="form-action span-2"><button class="button primary" type="submit" @disabled(!$submission->isEditable())>Simpan pelengkap laporan</button></div>
                    <small class="draft-indicator span-2" data-draft-indicator aria-live="polite">Aktivitas aktual tetap menjadi sumber durasi dan volume laporan.</small>
                </form>
                @else
                    <div class="empty-activities progress-source-empty">Catat aktivitas aktual terlebih dahulu agar pekerjaan dapat dirangkum otomatis.</div>
                @endif

                <details class="planned-work-panel" @if(old('entry_mode') === 'planned') open @endif>
                    <summary>+ Tambah aktivitas (Akan dikerjakan)</summary>
                    <form method="POST" action="{{ route('workload.progress.store') }}" class="activity-form" data-draft-form data-draft-key="progress-planned-{{ $submission->id }}">@csrf
                        <input type="hidden" name="entry_mode" value="planned"><input type="hidden" name="status" value="planned"><input type="hidden" name="progress_percentage" value="0">
                        <label class="field"><span>Tanggal laporan</span><input type="date" name="report_date" min="{{ $period->period_start->toDateString() }}" max="{{ min(today(), $period->period_start->copy()->endOfMonth())->toDateString() }}" value="{{ old('entry_mode') === 'planned' ? old('report_date') : (today()->betweenIncluded($period->period_start, $period->period_start->copy()->endOfMonth()) ? today()->toDateString() : $period->period_start->copy()->endOfMonth()->toDateString()) }}" required></label>
                        <label class="field"><span>Target mulai atau selesai</span><input type="date" name="target_date" value="{{ old('entry_mode') === 'planned' ? old('target_date') : '' }}" required></label>
                        <label class="field"><span>Kategori</span><div class="editable-select"><input name="category" list="category-options" value="{{ old('entry_mode') === 'planned' ? old('category') : '' }}" placeholder="Contoh: Development" maxlength="100" autocomplete="off" required><i aria-hidden="true"></i></div></label>
                        <label class="field"><span>Nama pekerjaan</span><input name="name" value="{{ old('entry_mode') === 'planned' ? old('name') : '' }}" placeholder="Contoh: Menyusun kamus kompetensi" maxlength="255" required></label>
                        <label class="field span-2"><span>Ringkasan rencana</span><textarea name="progress_summary" rows="3" maxlength="3000" placeholder="Jelaskan pekerjaan yang akan dilakukan." required>{{ old('entry_mode') === 'planned' ? old('progress_summary') : '' }}</textarea></label>
                        <label class="field span-2"><span>Kendala awal <em>opsional</em></span><textarea name="obstacle_note" rows="3" maxlength="3000">{{ old('entry_mode') === 'planned' ? old('obstacle_note') : '' }}</textarea></label>
                        <label class="field span-2"><span>Langkah yang akan diambil</span><textarea name="action_note" rows="3" maxlength="3000" required>{{ old('entry_mode') === 'planned' ? old('action_note') : '' }}</textarea></label>
                        <div class="form-action span-2"><button class="button secondary" type="submit" @disabled(!$submission->isEditable())>Simpan rencana pekerjaan</button></div>
                    </form>
                </details>
                <div class="progress-list">
                    @forelse($submission->progressItems as $progress)
                    <article>
                        <div class="progress-list-main"><span class="progress-state {{ $progress->status->value }}">{{ $progress->status->label() }}</span><strong>{{ $progress->name }}</strong><small>{{ $progress->category }} · {{ $progress->report_date->translatedFormat('d M Y') }} · {{ $progress->progress_percentage }}%</small><p>{{ $progress->progress_summary }}</p>@if($progress->obstacle_note)<small><b>Kendala:</b> {{ $progress->obstacle_note }}</small>@endif<small><b>Langkah:</b> {{ $progress->action_note }}</small>@if($progress->target_date)<small><b>Target:</b> {{ $progress->target_date->translatedFormat('d M Y') }}</small>@endif</div>
                        @if($submission->isEditable())<form method="POST" action="{{ route('workload.progress.destroy', $progress) }}">@csrf @method('DELETE')<button type="submit" class="icon-delete" aria-label="Hapus progres {{ $progress->name }}">×</button></form>@endif
                    </article>
                    @empty<div class="empty-activities">Belum ada progres pekerjaan untuk laporan HRD.</div>@endforelse
                </div>
            </section>
            @endif
            <datalist id="category-options">@foreach($categoryOptions as $option)<option value="{{ $option->label }}"></option>@endforeach</datalist>
        </div>
        <aside class="summary-card">
            <p class="eyebrow">RINGKASAN SAYA</p><h2>{{ $metrics['utilization'] !== null ? number_format($metrics['utilization'],1,',','.') .'%' : '—' }}</h2><span class="status-badge {{ $metrics['status']->value }}"><i></i>{{ $metrics['status']->label() }}</span>
            <dl><div><dt>Kapasitas efektif</dt><dd>{{ number_format($metrics['effective_minutes']/60,1,',','.') }} jam</dd></div><div><dt>Waktu aktual tercatat</dt><dd>{{ number_format($metrics['required_minutes']/60,1,',','.') }} jam</dd></div><div><dt>Jumlah catatan</dt><dd>{{ $submission->activities->count() }}</dd></div></dl>
            @if($submission->canBeSubmitted())<form method="POST" action="{{ route('workload.submit') }}" data-draft-form data-draft-key="submission-{{ $submission->id }}">@csrf<label class="field"><span>Catatan untuk atasan <em>opsional</em></span><textarea name="member_note" rows="3" placeholder="Konteks penting bulan ini">{{ old('member_note',$submission->member_note) }}</textarea></label><small class="draft-indicator" data-draft-indicator aria-live="polite">Catatan disimpan lokal sampai pengajuan berhasil.</small><button class="button primary" type="submit">{{ $submission->status === \App\Enums\SubmissionStatus::RevisionRequired ? 'Ajukan ulang untuk validasi' : 'Ajukan untuk validasi' }}</button></form>@elseif($submission->isEditable())<div class="locked-note">Aktivitas tetap dapat dicatat. Ringkasan baru dapat diajukan setelah periode {{ $period->period_start->translatedFormat('F Y') }} selesai.</div>@else<div class="locked-note">Data terkunci selama proses validasi. Riwayat perubahan tetap tersimpan.</div>@endif
        </aside>
    </div>
    @endif
</main>
</body></html>
