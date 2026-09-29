<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Http\Requests\ReviewSubmissionRequest;
use App\Models\UtilizationThreshold;
use App\Models\WorkloadSubmission;
use App\Services\SubmissionWorkflow;
use App\Services\WorkloadCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request, WorkloadCalculator $calculator): View
    {
        $submissions = $this->scope($request)->with(['user', 'workPeriod', 'capacity', 'activities.workTypeOption'])->latest('submitted_at')->get();
        $items = $submissions->map(fn ($submission) => [
            'submission' => $submission,
            'metrics' => $calculator->summarize($submission, UtilizationThreshold::effectiveFor($submission->workPeriod)),
        ]);

        return view('reviews.index', compact('items'));
    }

    public function update(ReviewSubmissionRequest $request, WorkloadSubmission $submission, SubmissionWorkflow $workflow): RedirectResponse
    {
        abort_unless($this->scope($request)->whereKey($submission->id)->exists(), 403);
        $workflow->review($submission, SubmissionStatus::from($request->validated('status')), $request->user(), $request->validated('note'));

        return back()->with('success', 'Keputusan validasi berhasil disimpan.');
    }

    private function scope(Request $request)
    {
        $actor = $request->user();
        $query = WorkloadSubmission::query()->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::Approved, SubmissionStatus::RevisionRequired]);

        return match ($actor->role) {
            UserRole::Manager => $query->whereHas('user', fn ($q) => $q->where('supervisor_id', $actor->id)),
            UserRole::ProcessOwner => $query->whereHas('user', fn ($q) => $q->where('organizational_unit_id', $actor->organizational_unit_id)),
            UserRole::Administrator => $query,
            default => $query->whereRaw('1 = 0'),
        };
    }
}
