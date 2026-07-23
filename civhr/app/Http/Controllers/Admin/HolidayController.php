<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin → Holidays. The working-day count on 6.C skips these dates, so the
 * admins keep the list current — each year's proclamation (plus the separate
 * Eid proclamations) is typed in here, no deploy needed.
 */
class HolidayController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Holidays/Index', [
            'holidays' => Holiday::orderByDesc('date')->get()
                ->map(fn ($h) => [
                    'id'    => $h->id,
                    'date'  => $h->date->toDateString(),
                    'label' => $h->date->format('D · M j, Y'),
                    'name'  => $h->name,
                    'year'  => $h->date->year,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // whereDate, not the unique: rule — the stored value carries the
            // cast's time component, which a plain equality check would miss.
            'date' => ['required', 'date', function ($attribute, $value, $fail) {
                if (Holiday::whereDate('date', $value)->exists()) {
                    $fail('That date is already on the holiday list.');
                }
            }],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Holiday::create($data);

        return back()->with('success', 'Holiday added. Leave filed from now on will skip it.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('success', 'Holiday removed. Already-filed leaves keep their day count.');
    }
}
