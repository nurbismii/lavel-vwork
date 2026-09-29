<?php

namespace App\Http\Controllers;

use App\Enums\WorkloadStatus;
use App\Models\UtilizationThreshold;
use App\Models\WorkPeriod;
use App\Services\AuditLogger;
use App\Services\VisibleTeam;
use App\Services\WorkloadCalculator;
use App\Services\XlsxReportExporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function print(Request $request, WorkloadCalculator $calculator, VisibleTeam $visibleTeam): View
    {
        [$period, $rows] = $this->reportData($request, $calculator, $visibleTeam);

        return view('reports.print', compact('period', 'rows'));
    }

    public function csv(Request $request, WorkloadCalculator $calculator, VisibleTeam $visibleTeam, AuditLogger $audit): StreamedResponse
    {
        [$period, $rows] = $this->reportData($request, $calculator, $visibleTeam);
        abort_unless($period, 404);
        $audit->record('report.csv_exported', $period, null, ['filters' => $request->only(['period', 'unit', 'status'])], $request);
        $filename = 'beban-kerja-'.$period->period_start->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($request, $period, $rows) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Laporan Beban Kerja Tim']);
            fputcsv($output, ['Periode', $period->period_start->translatedFormat('F Y')]);
            fputcsv($output, ['Dibuat oleh', $this->safeCell($request->user()->name)]);
            fputcsv($output, ['Waktu pembuatan', now()->toDateTimeString()]);
            fputcsv($output, []);
            fputcsv($output, ['ID Anggota', 'Nama', 'Unit', 'Jabatan', 'Status Validasi', 'Kapasitas Efektif (jam)', 'Waktu Aktual (jam)', 'Utilisasi (%)', 'Status Beban']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $this->safeCell($row['user']->employee_code), $this->safeCell($row['user']->name),
                    $this->safeCell($row['user']->organizationalUnit?->name), $this->safeCell($row['user']->position),
                    $row['submission']?->status?->label() ?? 'Belum mengisi', round($row['metrics']['effective_minutes'] / 60, 2),
                    round($row['metrics']['required_minutes'] / 60, 2), $row['metrics']['utilization'], $row['metrics']['status']->label(),
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(Request $request, WorkloadCalculator $calculator, VisibleTeam $visibleTeam, AuditLogger $audit, XlsxReportExporter $exporter): BinaryFileResponse
    {
        [$period, $rows] = $this->reportData($request, $calculator, $visibleTeam);
        abort_unless($period, 404);
        $audit->record('report.xlsx_exported', $period, null, ['filters' => $request->only(['period', 'unit', 'status'])], $request);

        return $exporter->download($rows, $period, $request->user());
    }

    private function reportData(Request $request, WorkloadCalculator $calculator, VisibleTeam $visibleTeam): array
    {
        $request->validate([
            'period' => ['nullable', 'integer', 'exists:work_periods,id'],
            'unit' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'status' => ['nullable', Rule::enum(WorkloadStatus::class)],
        ]);
        $period = $request->filled('period')
            ? WorkPeriod::query()->findOrFail($request->integer('period'))
            : WorkPeriod::query()->latest('period_start')->first();
        if (! $period) {
            return [null, collect()];
        }
        $visibleUnitIds = $visibleTeam->users($request->user())->whereNotNull('organizational_unit_id')
            ->distinct()->pluck('organizational_unit_id');
        if ($request->filled('unit')) {
            abort_unless($visibleUnitIds->contains($request->integer('unit')), 403);
        }
        $threshold = UtilizationThreshold::effectiveFor($period);
        $rows = $visibleTeam->users($request->user())
            ->when($request->filled('unit'), fn ($query) => $query->where('organizational_unit_id', $request->integer('unit')))
            ->with('organizationalUnit')
            ->with(['workloadSubmissions' => fn ($query) => $query->where('work_period_id', $period->id)->with(['capacity', 'activities'])])
            ->get()->map(function ($user) use ($calculator, $threshold) {
                $submission = $user->workloadSubmissions->first();
                $metrics = $submission
                    ? $calculator->summarize($submission, $threshold)
                    : ['effective_minutes' => 0, 'required_minutes' => 0, 'utilization' => null, 'status' => WorkloadStatus::Unavailable, 'by_type' => []];

                return compact('user', 'submission', 'metrics');
            });
        if ($request->filled('status')) {
            $rows = $rows->filter(fn ($row) => $row['metrics']['status']->value === $request->string('status')->toString());
        }

        return [$period, $rows];
    }

    private function safeCell(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
