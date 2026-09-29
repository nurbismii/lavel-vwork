<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CapacityStandardRequest;
use App\Http\Requests\WorkPeriodRequest;
use App\Http\Requests\WorkPeriodUpdateRequest;
use App\Models\WorkPeriod;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PeriodController extends Controller
{
    public function index(): View
    {
        return view('admin.periods', ['periods' => WorkPeriod::query()->withCount('submissions')->latest('period_start')->get()]);
    }

    public function store(WorkPeriodRequest $request, AuditLogger $audit): RedirectResponse
    {
        $this->ensurePeriodStartsOnFirstDay($request->validated('period_start'));

        DB::transaction(function () use ($request, $audit) {
            $period = WorkPeriod::query()->create([...$request->validated(), 'status' => 'draft']);
            $audit->record('period.created', $period, null, $period->toArray(), $request);
        });

        return back()->with('success', 'Periode draft berhasil dibuat.');
    }

    public function update(WorkPeriodUpdateRequest $request, WorkPeriod $period, AuditLogger $audit): RedirectResponse
    {
        $this->ensurePeriodStartsOnFirstDay($request->validated('period_start'));

        DB::transaction(function () use ($request, $period, $audit) {
            $lockedPeriod = WorkPeriod::query()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedPeriod->status !== 'draft') {
                throw ValidationException::withMessages([
                    'period' => 'Data periode hanya dapat diubah ketika periode masih draft.',
                ]);
            }

            $before = $lockedPeriod->toArray();
            $lockedPeriod->update($request->validated());
            $audit->record('period.updated', $lockedPeriod, $before, $lockedPeriod->fresh()->toArray(), $request);
        });

        return back()->with('success', 'Data periode berhasil diperbarui.');
    }

    public function open(Request $request, WorkPeriod $period, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $period, $audit) {
            WorkPeriod::query()->lockForUpdate()->get();
            $period->refresh();
            if (WorkPeriod::query()->where('status', 'open')->whereKeyNot($period->id)->exists()) {
                throw ValidationException::withMessages(['period' => 'Tutup periode aktif sebelum membuka periode lain.']);
            }
            if (! in_array($period->status, ['draft', 'locked'], true)) {
                throw ValidationException::withMessages(['period' => 'Hanya periode draft atau terkunci yang dapat dibuka.']);
            }
            $before = $period->toArray();
            $period->update(['status' => 'open', 'opened_by' => $request->user()->id, 'opened_at' => now(), 'locked_by' => null, 'locked_at' => null]);
            $audit->record('period.opened', $period, $before, $period->fresh()->toArray(), $request);
        });

        return back()->with('success', 'Periode berhasil dibuka.');
    }

    public function updateCapacityStandard(CapacityStandardRequest $request, WorkPeriod $period, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $period, $audit) {
            $lockedPeriod = WorkPeriod::query()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedPeriod->status !== 'draft') {
                throw ValidationException::withMessages([
                    'capacity_standard' => 'Standar kapasitas hanya dapat diubah ketika periode masih draft.',
                ]);
            }

            $before = $lockedPeriod->toArray();
            $lockedPeriod->update($request->validated());
            $audit->record('period.capacity_standard_updated', $lockedPeriod, $before, $lockedPeriod->fresh()->toArray(), $request);
        });

        return back()->with('success', 'Standar kapasitas periode berhasil diperbarui.');
    }

    public function lock(Request $request, WorkPeriod $period, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $period, $audit) {
            $period->newQuery()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();
            $period->refresh();
            if (! $period->isOpen()) {
                throw ValidationException::withMessages(['period' => 'Hanya periode terbuka yang dapat dikunci.']);
            }
            $before = $period->toArray();
            $period->update(['status' => 'locked', 'locked_by' => $request->user()->id, 'locked_at' => now()]);
            $audit->record('period.locked', $period, $before, $period->fresh()->toArray(), $request);
        });

        return back()->with('success', 'Periode berhasil dikunci.');
    }

    public function reopen(Request $request, WorkPeriod $period, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        DB::transaction(function () use ($request, $period, $audit, $validated) {
            WorkPeriod::query()->lockForUpdate()->get();
            $period->refresh();
            if ($period->status !== 'locked') {
                throw ValidationException::withMessages(['period' => 'Hanya periode terkunci yang dapat dibuka kembali.']);
            }
            if (WorkPeriod::query()->where('status', 'open')->whereKeyNot($period->id)->exists()) {
                throw ValidationException::withMessages(['period' => 'Tutup periode aktif sebelum membuka kembali periode ini.']);
            }
            $before = $period->toArray();
            $period->update([
                'status' => 'open',
                'reopen_reason' => $validated['reason'],
                'opened_by' => $request->user()->id,
                'opened_at' => now(),
                'locked_by' => null,
                'locked_at' => null,
            ]);
            $audit->record('period.reopened', $period, $before, $period->fresh()->toArray(), $request);
        });

        return back()->with('success', 'Periode berhasil dibuka kembali.');
    }

    private function ensurePeriodStartsOnFirstDay(string $periodStart): void
    {
        if (! CarbonImmutable::parse($periodStart)->isStartOfMonth()) {
            throw ValidationException::withMessages([
                'period_start' => 'Periode harus menggunakan tanggal pertama bulan.',
            ]);
        }
    }
}
