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
            'site_logo' => ['nullable', 'string', 'max:500'],
            'site_logo_file' => ['nullable', 'image', 'max:5120'],
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
            'hero_banner_1_image' => ['nullable', 'string', 'max:500'],
            'hero_banner_1_file' => ['nullable', 'image', 'max:8192'],
            'hero_banner_2_image' => ['nullable', 'string', 'max:500'],
            'hero_banner_2_file' => ['nullable', 'image', 'max:8192'],
            'hero_banner_3_image' => ['nullable', 'string', 'max:500'],
            'hero_banner_3_file' => ['nullable', 'image', 'max:8192'],
            'hero_banners' => ['nullable', 'string', 'max:5000'],
            'hero_banners_list' => ['nullable', 'array', 'min:1', 'max:10'],
            'hero_banners_list.*' => ['nullable', 'string', 'max:500'],
            'hero_banner_files' => ['nullable', 'array', 'max:10'],
            'hero_banner_files.*' => ['nullable', 'image', 'max:8192'],
        ]);

        unset(
            $validated['site_logo_file'],
            $validated['hero_banner_1_file'],
            $validated['hero_banner_2_file'],
            $validated['hero_banner_3_file'],
            $validated['hero_banners_list'],
            $validated['hero_banner_files'],
        );
        $validated['site_logo'] = trim((string) ($validated['site_logo'] ?? ''));

        if ($request->hasFile('site_logo_file')) {
            $storedPath = $request->file('site_logo_file')->store('settings', 'public');
            $validated['site_logo'] = '/storage/'.ltrim($storedPath, '/');
        }

        if ($request->has('hero_banners_list')) {
            $rawBanners = (array) $request->input('hero_banners_list', []);
            $resolvedBanners = [];

            foreach (array_values($rawBanners) as $idx => $rawUrl) {
                $bannerUrl = trim((string) ($rawUrl ?? ''));

                if ($request->hasFile("hero_banner_files.{$idx}")) {
                    $storedBannerPath = $request->file("hero_banner_files.{$idx}")->store('settings/banners', 'public');
                    $bannerUrl = '/storage/'.ltrim($storedBannerPath, '/');
                }

                $resolvedBanners[] = $bannerUrl;
            }

            $validated['hero_banners'] = json_encode($resolvedBanners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
            $validated['hero_banner_1_image'] = $resolvedBanners[0] ?? '';
            $validated['hero_banner_2_image'] = $resolvedBanners[1] ?? '';
            $validated['hero_banner_3_image'] = $resolvedBanners[2] ?? '';
        } else {
            foreach ([1, 2, 3] as $index) {
                $imageKey = "hero_banner_{$index}_image";
                $fileKey = "hero_banner_{$index}_file";
                $validated[$imageKey] = trim((string) ($validated[$imageKey] ?? ''));

                if ($request->hasFile($fileKey)) {
                    $storedBannerPath = $request->file($fileKey)->store('settings/banners', 'public');
                    $validated[$imageKey] = '/storage/'.ltrim($storedBannerPath, '/');
                }
            }
        }

        SiteSetting::updateSettings($validated);

        return to_route('admin.settings.index')
            ->with('toast', 'Đã cập nhật cài đặt hệ thống thành công.');
    }
}
