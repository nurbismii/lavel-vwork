const sidebar = document.querySelector('#sidebar');
const backdrop = document.querySelector('.sidebar-backdrop');
const menuButton = document.querySelector('[data-sidebar-open]');

const toggleSidebar = (open) => {
    sidebar?.classList.toggle('open', open);
    backdrop?.classList.toggle('open', open);
    menuButton?.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
};

menuButton?.addEventListener('click', () => toggleSidebar(true));
document.querySelectorAll('[data-sidebar-close]').forEach((element) => element.addEventListener('click', () => toggleSidebar(false)));
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') toggleSidebar(false); });

const toast = document.querySelector('[data-toast]');
let toastTimer;
const showToast = (message) => {
    if (!toast) return;
    toast.querySelector('span').textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
};

const clearedDraft = document.body.dataset.clearDraft;
if (clearedDraft) {
    try { localStorage.removeItem(`ruangkerja:draft:${clearedDraft}`); } catch { /* Penyimpanan lokal dapat dinonaktifkan browser. */ }
}

document.querySelectorAll('[data-draft-form]').forEach((form) => {
    const storageKey = `ruangkerja:draft:${form.dataset.draftKey}`;
    const indicator = form.querySelector('[data-draft-indicator]');
    const fields = [...form.elements].filter((field) => field.name && !['_token', '_method'].includes(field.name) && !['submit', 'button'].includes(field.type));

    try {
        const saved = JSON.parse(localStorage.getItem(storageKey) ?? 'null');
        if (saved) {
            fields.forEach((field) => {
                if (Object.hasOwn(saved, field.name)) field.value = saved[field.name];
                else if (field.name === 'actual_duration' && Object.hasOwn(saved, 'actual_minutes')) field.value = saved.actual_minutes;
            });
            if (indicator) indicator.textContent = 'Draf lokal dipulihkan. Simpan untuk mengirim ke server.';
        }
    } catch { /* Abaikan draf rusak atau storage yang tidak tersedia. */ }

    form.addEventListener('input', () => {
        try {
            const values = Object.fromEntries(fields.map((field) => [field.name, field.value]));
            localStorage.setItem(storageKey, JSON.stringify(values));
            if (indicator) indicator.textContent = 'Draf tersimpan otomatis di perangkat ini.';
        } catch {
            if (indicator) indicator.textContent = 'Draf lokal tidak tersedia. Simpan sebelum meninggalkan halaman.';
        }
    });
});

const rows = [...document.querySelectorAll('[data-member]')];
const search = document.querySelector('[data-search]');
const resultCount = document.querySelector('[data-result-count]');
const emptyState = document.querySelector('[data-empty-state]');

const applyTableFilters = () => {
    const term = search?.value.trim().toLowerCase() ?? '';
    let visible = 0;

    rows.forEach((row) => {
        const matches = row.dataset.name.includes(term);
        row.hidden = !matches;
        if (matches) visible += 1;
    });

    if (resultCount) resultCount.textContent = `Menampilkan ${visible} dari ${rows.length} anggota`;
    if (emptyState) emptyState.hidden = visible !== 0;
};

search?.addEventListener('input', applyTableFilters);
document.querySelector('[data-scroll-team]')?.addEventListener('click', () => document.querySelector('#team-members')?.scrollIntoView({ behavior: 'smooth' }));

rows.forEach((row) => {
    row.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && row.dataset.detailUrl) window.location.assign(row.dataset.detailUrl);
    });
});

const followUpOwner = document.querySelector('[data-follow-up-owner]');
const followUpSubmission = document.querySelector('[data-follow-up-submission]');
const followUpSubmissionHelp = document.querySelector('[data-follow-up-submission-help]');
let submissionRequest;

const resetSubmissionOptions = (label, disabled = true) => {
    if (!followUpSubmission) return;

    followUpSubmission.replaceChildren(new Option(label, ''));
    followUpSubmission.disabled = disabled;
};

const loadOwnerSubmissions = async () => {
    if (!followUpOwner || !followUpSubmission) return;

    submissionRequest?.abort();
    const ownerId = followUpOwner.value;
    if (!ownerId) {
        resetSubmissionOptions('Pilih owner terlebih dahulu');
        if (followUpSubmissionHelp) followUpSubmissionHelp.textContent = 'Daftar pengajuan akan mengikuti owner yang dipilih.';
        return;
    }

    submissionRequest = new AbortController();
    resetSubmissionOptions('Memuat pengajuan...');
    if (followUpSubmissionHelp) followUpSubmissionHelp.textContent = 'Mengambil pengajuan milik owner terpilih.';

    try {
        const url = followUpSubmission.dataset.urlTemplate.replace('OWNER_ID', encodeURIComponent(ownerId));
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: submissionRequest.signal,
        });
        if (!response.ok) throw new Error('Pengajuan tidak dapat dimuat.');

        const { submissions } = await response.json();
        const selectedId = followUpSubmission.dataset.selected;
        resetSubmissionOptions('Level tim / tanpa pengajuan', false);
        submissions.forEach((submission) => {
            const option = new Option(submission.label, String(submission.id));
            option.selected = String(submission.id) === selectedId;
            followUpSubmission.add(option);
        });
        followUpSubmission.dataset.selected = '';
        if (followUpSubmissionHelp) {
            followUpSubmissionHelp.textContent = submissions.length
                ? `${submissions.length} pengajuan ditemukan untuk owner ini.`
                : 'Owner ini belum memiliki pengajuan. Tindak lanjut tetap dapat dibuat pada level tim.';
        }
    } catch (error) {
        if (error.name === 'AbortError') return;
        resetSubmissionOptions('Gagal memuat pengajuan');
        if (followUpSubmissionHelp) followUpSubmissionHelp.textContent = 'Pengajuan gagal dimuat. Pilih ulang owner atau muat ulang halaman.';
    }
};

followUpOwner?.addEventListener('change', () => {
    if (followUpSubmission) followUpSubmission.dataset.selected = '';
    loadOwnerSubmissions();
});
loadOwnerSubmissions();

const durationInput = document.querySelector('[data-duration-input]');
if (durationInput) {
    const value = durationInput.querySelector('input');
    const unit = durationInput.querySelector('select');
    const preview = document.querySelector('[data-duration-preview]');
    const updateDurationPreview = () => {
        const multiplier = unit.value === 'day' ? Number(durationInput.dataset.hoursPerDay) * 60 : unit.value === 'hour' ? 60 : 1;
        const minutes = Math.round(Number(value.value) * multiplier);
        preview.textContent = !value.value ? '' : minutes < 1 || minutes > 1440
            ? 'Durasi setelah konversi harus antara 1 dan 1.440 menit.'
            : `Setara ${minutes.toLocaleString('id-ID')} menit (${(minutes / 60).toLocaleString('id-ID', { maximumFractionDigits: 2 })} jam).`;
    };
    durationInput.addEventListener('input', updateDurationPreview);
    durationInput.addEventListener('change', updateDurationPreview);
    updateDurationPreview();
}

// Bullet tetap berupa teks biasa agar tersimpan bersama draf dan laporan.
document.querySelectorAll('[data-auto-bullet]').forEach((field) => {
    let wasEmpty = field.value.length === 0;
    field.addEventListener('input', (event) => {
        if (event.isComposing) return;
        if (wasEmpty && field.value.trim() && !/^[ \t]*[•*-] /.test(field.value)
            && (field.maxLength < 0 || field.value.length + 2 <= field.maxLength)) {
            const start = field.selectionStart;
            const end = field.selectionEnd;
            field.setRangeText('• ', 0, 0, 'preserve');
            field.setSelectionRange(start + 2, end + 2);
        }
        wasEmpty = field.value.length === 0;
    });
    field.addEventListener('compositionend', () => field.dispatchEvent(new Event('input', { bubbles: true })));

    field.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey || event.isComposing) return;
        const start = field.selectionStart;
        const end = field.selectionEnd;
        const lineStart = start === 0 ? 0 : field.value.lastIndexOf('\n', start - 1) + 1;
        const lineEnd = field.value.indexOf('\n', start);
        const line = field.value.slice(lineStart, lineEnd < 0 ? field.value.length : lineEnd);
        const prefix = line.match(/^[ \t]*[•*-] /)?.[0];
        const emptyBullet = prefix && !line.slice(prefix.length).trim();
        const beforeCaret = field.value.slice(lineStart, start);
        const replacement = emptyBullet ? '' : `${prefix ? '' : '• '}${beforeCaret}\n${prefix ?? '• '}`;
        const replaceEnd = emptyBullet ? lineStart + line.length : end;
        event.preventDefault();
        if (field.maxLength >= 0 && field.value.length - (replaceEnd - lineStart) + replacement.length > field.maxLength) return;
        field.setRangeText(replacement, lineStart, replaceEnd, 'end');
        field.dispatchEvent(new Event('input', { bubbles: true }));
    });
});
