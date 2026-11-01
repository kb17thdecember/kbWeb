<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class SiteSettingController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        return Inertia::render('settings/Index', [
            'settings' => SiteSetting::allSettings(),
            'defaults' => SiteSetting::defaults(),
            ...$dashboardStats->summary(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'company_tagline' => ['required', 'string', 'max:160'],
            'announcement_text' => ['required', 'string', 'max:255'],
            'footer_description' => ['required', 'string', 'max:500'],
            'copyright_text' => ['required', 'string', 'max:255'],
            'hotline' => ['required', 'string', 'max:50'],
            'zalo_phone' => ['required', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'working_hours' => ['required', 'string', 'max:160'],
            'facebook_url' => ['required', 'string', 'max:255'],
            'linkedin_url' => ['required', 'string', 'max:255'],
            'zalo_url' => ['required', 'string', 'max:255'],
        ]);

        SiteSetting::updateSettings($validated);

        return to_route('admin.settings.index')
            ->with('toast', 'Đã cập nhật cài đặt hệ thống thành công.');
    }
}
