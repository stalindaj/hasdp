<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LeaveWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A person's e-signature: a scan of their wet signature, printed over their
 * name on CS Form 6.
 *
 * Everyone manages their own; an admin may also set one for someone else —
 * the Director for Personnel signs the form but rarely logs in. Files live on
 * the private disk and are served only through the guarded route below, so a
 * signature can never be lifted from a public URL.
 */
class SignatureController extends Controller
{
    public function store(Request $request, User $user)
    {
        $this->authorizeManage($request, $user);

        $request->validate([
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'signature.image' => 'Upload a picture of your signature (PNG works best — a transparent background prints cleanest).',
        ]);

        // One signature per person: replace rather than accumulate.
        if ($user->signature_path) {
            Storage::delete($user->signature_path);
        }

        $user->update([
            'signature_path' => $request->file('signature')->store("signatures/{$user->id}"),
        ]);

        return back()->with('success', 'Signature saved. It will print above the name on CS Form No. 6.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeManage($request, $user);

        if ($user->signature_path) {
            Storage::delete($user->signature_path);
            $user->update(['signature_path' => null]);
        }

        return back()->with('success', 'Signature removed.');
    }

    /** Serve the image to any signed-in user — it prints on shared forms. */
    public function show(Request $request, User $user)
    {
        abort_unless($user->signature_path && Storage::exists($user->signature_path), 404);

        return response()->file(Storage::path($user->signature_path));
    }

    /** Your own signature, or anyone's if you are an admin. */
    private function authorizeManage(Request $request, User $user): void
    {
        $actor = $request->user();

        abort_unless(
            $actor->id === $user->id || LeaveWorkflow::isAdmin($actor),
            403,
            'You may only change your own signature.'
        );
    }
}
