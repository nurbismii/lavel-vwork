<?php

namespace App\Http\Controllers;

use App\Http\Requests\MyFollowUpStatusRequest;
use App\Models\FollowUpAction;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MyFollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])],
        ]);

        $baseQuery = FollowUpAction::query()->where('owner_id', $request->user()->id);
        $actions = (clone $baseQuery)
            ->with(['organizationalUnit', 'submission.user', 'submission.workPeriod'])
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->orderByRaw('target_date is null')
            ->orderBy('target_date')
            ->latest('id')
            ->get();

        return view('follow-ups.mine', [
            'actions' => $actions,
            'openCount' => (clone $baseQuery)->whereIn('status', ['open', 'in_progress'])->count(),
            'dueCount' => (clone $baseQuery)
                ->whereDate('target_date', '<=', today())
                ->whereIn('status', ['open', 'in_progress'])
                ->count(),
            'completedCount' => (clone $baseQuery)->where('status', 'completed')->count(),
        ]);
    }

    public function update(MyFollowUpStatusRequest $request, FollowUpAction $action, AuditLogger $audit): RedirectResponse
    {
        abort_unless($action->owner_id === $request->user()->id, 403);
        abort_if($action->status === 'cancelled', 422, 'Tindak lanjut yang dibatalkan tidak dapat diperbarui oleh owner.');

        $before = $action->toArray();
        DB::transaction(function () use ($action, $request, $before, $audit): void {
            $action->update($request->validated());
            $audit->record('follow_up.owner_status_updated', $action, $before, $action->fresh()->toArray(), $request);
        });

        return back()->with('success', 'Status tindak lanjut berhasil diperbarui.');
    }
}
