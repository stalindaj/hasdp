<?php

namespace App\Http\Controllers;

use App\Models\LdEntry;
use App\Support\LeaveWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Learning & Development submissions.
 *
 * The employee files a training with photo proof (certificate and/or a photo
 * taken during the training — at least one required); an admin approves or
 * rejects it, and only approved hours count toward the yearly target.
 *
 * Proof images hold personal data, so they are stored on the private disk and
 * served only to the owner and admins through the file route below — never as
 * public files.
 */
class LdController extends Controller
{
    /** Employee submits a training for approval. */
    public function store(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee record.');

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:999'],
            'date'  => ['required', 'date', 'before_or_equal:today'],
            'certificate' => ['nullable', 'required_without:photo', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photo'       => ['nullable', 'required_without:certificate', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'certificate.required_without' => 'Attach the certificate, a photo from the training, or both.',
            'photo.required_without'       => 'Attach the certificate, a photo from the training, or both.',
        ]);

        $dir = "ld/{$employee->id}";

        $employee->ldEntries()->create([
            'title'  => $request->string('title'),
            'hours'  => $request->input('hours'),
            'date'   => $request->input('date'),
            'status' => LdEntry::PENDING,
            'certificate_path' => $request->file('certificate')?->store($dir),
            'photo_path'       => $request->file('photo')?->store($dir),
            'submitted_by'     => $request->user()->id,
        ]);

        return back()->with('success', 'Training submitted. The hours will count once an admin approves it.');
    }

    /** Admin approves or rejects a submission (re-decidable any time). */
    public function decide(Request $request, LdEntry $entry)
    {
        abort_unless(LeaveWorkflow::isAdmin($request->user()), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in([LdEntry::APPROVED, LdEntry::REJECTED])],
            'remarks'  => [Rule::requiredIf($request->input('decision') === LdEntry::REJECTED), 'nullable', 'string', 'max:255'],
        ]);

        $entry->update([
            'status'     => $data['decision'],
            'remarks'    => $data['decision'] === LdEntry::REJECTED ? $data['remarks'] : null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back()->with('success', $data['decision'] === LdEntry::APPROVED
            ? 'L&D approved — the hours now count.'
            : 'L&D rejected.');
    }

    /** Serve a proof image to the owner or an admin. */
    public function file(Request $request, LdEntry $entry, string $kind)
    {
        $user = $request->user();

        $isOwner = $user->employee && (int) $user->employee->id === (int) $entry->employee_id;
        abort_unless($isOwner || LeaveWorkflow::isAdmin($user), 403);

        $path = match ($kind) {
            'certificate' => $entry->certificate_path,
            'photo'       => $entry->photo_path,
            default       => null,
        };

        abort_unless($path && Storage::exists($path), 404);

        return response()->file(Storage::path($path));
    }
}
