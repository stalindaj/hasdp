<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LdEntry;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationAction;
use App\Models\LeaveCreditEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The audit trail — every consequential act in the system, in one stream:
 * leave filings and decisions, admin-recorded leaves, signed-form uploads,
 * L&D approvals, and credit adjustments.
 *
 * Superadmin only (see the `superadmin` middleware on the routes): ordinary
 * admins appear in this log, so they must not be able to read or edit it.
 * Nothing here is writable — the underlying rows are append-only.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $events = $this->events();

        return Inertia::render('Admin/Audit/Index', [
            'events' => $events->take(500)->values(),
            'total'  => $events->count(),
            'counts' => [
                'leave'  => $events->where('area', 'Leave')->count(),
                'ld'     => $events->where('area', 'L&D')->count(),
                'credit' => $events->where('area', 'Credits')->count(),
            ],
        ]);
    }

    /** The same stream as a CSV, for filing or offline review. */
    public function export(): StreamedResponse
    {
        $events = $this->events();
        $name = 'civdir-audit-'.now()->format('Y-m-d-Hi').'.csv';

        return response()->streamDownload(function () use ($events) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp', 'Area', 'Action', 'By', 'Subject', 'Details']);

            foreach ($events as $e) {
                fputcsv($out, [$e['at'], $e['area'], $e['action'], $e['by'], $e['subject'], $e['details']]);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Every tracked event, newest first. */
    private function events(): Collection
    {
        $leave = LeaveApplicationAction::with(['user:id,name', 'application.leaveType:id,name', 'application.employee:id,emp_no,first_name,last_name'])
            ->latest('id')
            ->limit(2000)
            ->get()
            ->map(function ($a) {
                $app = $a->application;

                return [
                    'at'      => $a->created_at->format('Y-m-d H:i'),
                    'sort'    => $a->created_at->timestamp,
                    'area'    => 'Leave',
                    'action'  => $a->action,
                    'by'      => $a->user?->name ?? 'System',
                    'subject' => $app?->applicant_name ?: '—',
                    'details' => trim(implode(' · ', array_filter([
                        $app?->leaveType?->name,
                        // decimal:2 casts render "3.00" — show "3".
                        $app ? rtrim(rtrim((string) $app->working_days, '0'), '.').' day/s' : null,
                        $app?->inclusive_dates_text,
                        $a->remarks,
                    ]))),
                    'link' => $app ? route('leave.show', $app->id) : null,
                ];
            });

        $ld = LdEntry::with(['employee:id,first_name,last_name', 'submitter:id,name'])
            ->whereNotNull('decided_at')
            ->latest('decided_at')
            ->limit(1000)
            ->get()
            ->map(fn ($l) => [
                'at'      => $l->decided_at->format('Y-m-d H:i'),
                'sort'    => $l->decided_at->timestamp,
                'area'    => 'L&D',
                'action'  => $l->status,
                'by'      => \App\Models\User::find($l->decided_by)?->name ?? 'System',
                'subject' => trim($l->employee?->first_name.' '.$l->employee?->last_name),
                'details' => trim(implode(' · ', array_filter([
                    $l->title,
                    $l->hours.'h',
                    $l->date->format('M j, Y'),
                    $l->remarks,
                ]))),
                'link' => null,
            ]);

        // Manual balance corrections only — the monthly accrual is automatic
        // and would drown the trail.
        $credits = LeaveCreditEntry::with('employee:id,first_name,last_name')
            ->whereNull('period')
            ->whereNull('leave_application_id')
            ->latest('id')
            ->limit(1000)
            ->get()
            ->map(fn ($c) => [
                'at'      => $c->created_at->format('Y-m-d H:i'),
                'sort'    => $c->created_at->timestamp,
                'area'    => 'Credits',
                'action'  => 'adjusted',
                'by'      => $this->userFromNote($c->note),
                'subject' => trim($c->employee?->first_name.' '.$c->employee?->last_name),
                'details' => strtoupper($c->kind).' '.($c->amount > 0 ? '+' : '').$c->amount.' · '.$c->note,
                'link' => null,
            ]);

        return $leave->concat($ld)->concat($credits)->sortByDesc('sort')->values();
    }

    /** The ledger stores "… — by user #7"; resolve that to a name. */
    private function userFromNote(?string $note): string
    {
        if ($note && preg_match('/by user #(\d+)/', $note, $m)) {
            return \App\Models\User::find($m[1])?->name ?? 'User #'.$m[1];
        }

        return 'System';
    }
}
