<?php

namespace App\Http\Controllers\Api\Settings;

use App\Filament\Pages\AdminFrontendContentPage;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class BrandingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $setting = Setting::query()->first();
        $content = array_replace(
            AdminFrontendContentPage::defaults(),
            is_array($setting?->frontend_content) ? $setting->frontend_content : []
        );

        return response()->json([
            'software_name' => (string) ($setting?->software_name ?: config('app.name', 'PixFácil')),
            'desktop_logo' => $this->assetUrl($setting?->software_logo_white, 'pixfacil-v15/logo.svg'),
            'mobile_logo' => $this->assetUrl($setting?->pixfacil_mobile_logo ?: $setting?->software_logo_white, 'pixfacil-v15/logo.svg'),
            'loading_logo' => $this->assetUrl($setting?->pixfacil_loading_logo ?: $setting?->software_logo_black ?: $setting?->software_logo_white, 'pixfacil-v15/logo.svg'),
            'favicon' => $this->assetUrl($setting?->software_favicon, 'pixfacil-v15/icons/icon-192.png'),
            'content' => $content,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function assetUrl(?string $value, string $fallback): string
    {
        if (! filled($value)) {
            return asset($fallback);
        }

        $value = ltrim((string) $value, '/');
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if (str_starts_with($value, 'storage/')) {
            return asset($value);
        }

        if (str_starts_with($value, 'uploads/')) {
            return asset('storage/' . $value);
        }

        return asset('storage/' . $value);
    }
}
