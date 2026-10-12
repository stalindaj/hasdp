<?php

namespace App\Http\Controllers;

use App\Models\ReportCell;
use App\Support\LdReport;
use App\Support\LdReportExcel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The L&D accomplishment report (admin): Tables 1–5 and the monitoring
 * sheet, live from the approved filings, hand-editable cell by cell, and
 * downloadable as an Excel workbook.
 */
class LdReportController extends Controller
{
    public function index(Request $request)
    {
        $report = $this->report($request);

        return Inertia::render('Ld/Reports', [
            'year'    => $report->year,
            'quarter' => $report->quarter,
            'period'  => $report->periodLabel(),
            'years'   => range(now()->year, now()->year - 5),
            'tables'  => $report->tables(),
        ]);
    }

    /** Save a hand edit over one cell for this period. */
    public function cell(Request $request)
    {
        $report = $this->report($request);
        $data = $this->validatedCell($request, $report);

        ReportCell::updateOrCreate(
            ['report' => $data['report'], 'period' => $report->period(), 'row_key' => $data['row_key'], 'col' => $data['col']],
            ['value' => $data['value'] ?? null, 'updated_by' => $request->user()->id],
        );

        return back();
    }

    /** Drop a hand edit, so the cell shows the live figure again. */
    public function reset(Request $request)
    {
        $report = $this->report($request);
        $data = $this->validatedCell($request, $report);

        ReportCell::where([
            'report' => $data['report'], 'period' => $report->period(),
            'row_key' => $data['row_key'], 'col' => $data['col'],
        ])->delete();

        return back();
    }

    public function export(Request $request)
    {
        $report = $this->report($request);
        // Built inside storage/ — the shared host's system temp folder is not
        // reliably writable — and removed once sent.
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.'/ld-report-'.bin2hex(random_bytes(8)).'.xlsx';

        LdReportExcel::write($report, $path);

        $name = 'LD-Report-'.$report->period().'.xlsx';

        return response()->download($path, $name)->deleteFileAfterSend();
    }

    private function report(Request $request): LdReport
    {
        $data = $request->validate([
            'year'    => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['nullable', 'integer', 'between:0,4'],
        ]);

        return new LdReport((int) ($data['year'] ?? now()->year), (int) ($data['quarter'] ?? 0));
    }

    /** Only a real row's editable column may be overridden. */
    private function validatedCell(Request $request, LdReport $report): array
    {
        $data = $request->validate([
            'report'  => ['required', Rule::in(LdReport::REPORTS)],
            'row_key' => ['required', 'string', 'max:64'],
            'col'     => ['required', 'string', 'max:40'],
            'value'   => ['nullable', 'string', 'max:2000'],
        ]);

        $table = collect($report->tables())->firstWhere('report', $data['report']);
        $column = collect($table['columns'])->firstWhere('key', $data['col']);
        $row = collect($table['rows'])->firstWhere('key', $data['row_key']);

        abort_unless($column && $column['edit'] && $row, 422, 'That cell cannot be edited.');

        return $data;
    }
}
