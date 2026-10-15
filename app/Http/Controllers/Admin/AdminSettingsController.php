<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Models\AdminModel;
use App\Services\AdminTwoFactorService;
use App\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function index(Request $request, SystemSettingsService $settings, AdminTwoFactorService $twoFactor): View
    {
        $sections = [];
        foreach (SystemSettingsService::SECTIONS as $section) {
            $sections[$section] = ['values' => $settings->section($section), 'revision' => $settings->revision($section)];
        }

        return view('admin.settings.index', [
            'sections' => $sections,
            'security' => $twoFactor->setupViewData($request->user('admin'), $request->session()),
            'admins' => AdminModel::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request, string $section, SystemSettingsService $settings): RedirectResponse
    {
        $settings->save($section, $request->validated('values'), (int) $request->validated('revision'), $request->user('admin'));

        return redirect()->to(route('admin.settings.index').'#'.$section)
            ->with('success', ucfirst($section).' settings saved.');
    }
}
