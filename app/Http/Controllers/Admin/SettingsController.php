<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Runtime settings. The screen offers exactly what SiteSettings declares, so
 * an operator cannot be shown a switch that nothing reads.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'booleans' => SiteSettings::BOOLEANS,
            'values' => $this->settings->allBooleans(),
        ]);
    }

    /**
     * No FormRequest: the payload is a fixed set of checkboxes, and
     * saveBooleans() casts each declared key and ignores everything else —
     * validating a shape that is already allowlisted at the point of use
     * would restate the same rule in a second place.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->settings->saveBooleans((array) $request->input('settings', []));

        return redirect()->route('admin.settings.edit')->with('status', 'Настройките са запазени.');
    }
}
