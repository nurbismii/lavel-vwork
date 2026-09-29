<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = AuditLog::query()->with('actor')
            ->when($request->user()->role !== UserRole::Administrator, fn ($query) => $query->whereHas('actor', fn ($actor) => $actor->where('organizational_unit_id', $request->user()->organizational_unit_id)))
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', $request->string('action').'%'))
            ->when($request->filled('actor'), fn ($query) => $query->where('actor_id', $request->integer('actor')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest('created_at')->paginate(30)->withQueryString();
        $actors = User::query()
            ->when($request->user()->role !== UserRole::Administrator, fn ($query) => $query->where('organizational_unit_id', $request->user()->organizational_unit_id))
            ->orderBy('name')->get(['id', 'name']);

        return view('admin.audit', compact('logs', 'actors'));
    }
}
