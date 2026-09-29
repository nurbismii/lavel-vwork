<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UtilizationThresholdRequest;
use App\Models\UtilizationThreshold;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ThresholdController extends Controller
{
    public function index(): View
    {
        return view('admin.thresholds', ['thresholds' => UtilizationThreshold::query()->latest('effective_from')->get()]);
    }

    public function store(UtilizationThresholdRequest $request, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit) {
            $threshold = UtilizationThreshold::query()->create([...$request->validated(), 'created_by' => $request->user()->id]);
            $audit->record('threshold.created', $threshold, null, $threshold->toArray(), $request);
        });

        return back()->with('success', 'Ambang baru tersimpan dan akan berlaku prospektif.');
    }
}
