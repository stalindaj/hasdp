<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Your own profile picture, shown in the welcome circle on the dashboard.
 *
 * Like signatures, files live on the private disk and are served only to
 * signed-in users through the route below.
 */
class ProfilePhotoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
        ], [
            'photo.image' => 'Upload a picture (PNG, JPG or WEBP).',
            'photo.max'   => 'That picture is over 8 MB — try a smaller one.',
        ]);

        $user = $request->user();

        // One picture per person: replace rather than accumulate.
        if ($user->photo_path) {
            Storage::delete($user->photo_path);
        }

        $user->update(['photo_path' => $request->file('photo')->store("photos/{$user->id}")]);

        return back()->with('success', 'Profile picture updated.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->photo_path) {
            Storage::delete($user->photo_path);
            $user->update(['photo_path' => null]);
        }

        return back()->with('success', 'Profile picture removed.');
    }

    public function show(User $user)
    {
        abort_unless($user->photo_path && Storage::exists($user->photo_path), 404);

        return response()->file(Storage::path($user->photo_path), [
            'Cache-Control' => 'private, max-age=31536000',
        ]);
    }
}
