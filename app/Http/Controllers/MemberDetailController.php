<?php

namespace App\Http\Controllers;

use App\Enums\WorkloadStatus;
use App\Models\User;
use App\Models\UtilizationThreshold;
use App\Models\WorkPeriod;
use App\Services\VisibleTeam;
use App\Services\WorkloadCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberDetailController extends Controller
{
    public function __invoke(Request $request, User $user, VisibleTeam $visibleTeam, WorkloadCalculator $calculator): View
    {
        abort_unless($visibleTeam->users($request->user())->whereKey($user->id)->exists(), 403);

        $period = $request->filled('period')
            ? WorkPeriod::query()->findOrFail($request->integer('period'))
            : WorkPeriod::query()->orderByRaw("status = 'open' desc")->latest('period_start')->first();
        $submission = $period ? $user->workloadSubmissions()
            ->where('work_period_id', $period->id)
            ->with(['capacity', 'activities.workTypeOption', 'progressItems', 'validationHistories.actor', 'followUpActions.owner'])
            ->first() : null;
        $metrics = $submission
            ? $calculator->summarize($submission, UtilizationThreshold::effectiveFor($period))
            : ['effective_minutes' => 0, 'required_minutes' => 0, 'utilization' => null, 'status' => WorkloadStatus::Unavailable, 'by_type' => []];

        return view('members.show', [
            'member' => $user->load('organizationalUnit'),
            'period' => $period,
            'periods' => WorkPeriod::query()->latest('period_start')->get(),
            'submission' => $submission,
            'metrics' => $metrics,
        ]);
    }
}
