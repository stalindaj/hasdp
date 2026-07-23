<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\CreditLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin → Balances: every employee's VL / SL / Wellness / SPL in one grid.
 * Clicking a cell sets the balance to a typed value — recorded through the
 * ledger as an adjustment (with the reason), so the audit trail stays intact.
 */
class BalanceController extends Controller
{
    public function index()
    {
        $rows = Employee::query()
            ->whereNotNull('emp_no')
            ->where('emp_no', '!=', 'mission')   // the 7.C/D signatory record
            ->orderBy('last_name')
            ->get()
            ->map(fn ($e) => [
                'id'     => $e->id,
                'emp_no' => $e->emp_no,
                'name'   => trim($e->last_name.', '.$e->first_name),
            ] + CreditLedger::balances($e));

        return Inertia::render('Admin/Balances/Index', [
            'rows' => $rows,
            'totals' => [
                'vl'       => round($rows->sum('vl'), 2),
                'sl'       => round($rows->sum('sl'), 2),
                'wellness' => round($rows->sum('wellness'), 2),
                'spl'      => round($rows->sum('spl'), 2),
            ],
        ]);
    }

    /** Set one balance to a target value (stored as a ledger adjustment). */
    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'kind'   => ['required', Rule::in(['vl', 'sl', 'wellness', 'spl'])],
            'value'  => ['required', 'numeric', 'min:-999', 'max:999'],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        $current = CreditLedger::balances($employee)[$data['kind']];
        $delta = round((float) $data['value'] - $current, 2);

        if (abs($delta) < 0.005) {
            return back()->with('success', 'No change — the balance is already '.$current.'.');
        }

        CreditLedger::adjust(
            $employee,
            $data['kind'],
            $delta,
            $data['note'] ?: sprintf('Set %s to %s (was %s)', strtoupper($data['kind']), $data['value'], $current),
            $request->user()->id
        );

        return back()->with('success', sprintf(
            '%s for %s set to %s.',
            strtoupper($data['kind']),
            $employee->first_name.' '.$employee->last_name,
            $data['value']
        ));
    }
}
