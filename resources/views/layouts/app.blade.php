<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @php
        $setting = \Helper::getSetting();

        $resolveStorageAsset = function (?string $path, string $fallback) {
            if (blank($path)) return asset($fallback);

            $path = ltrim((string) $path, '/');
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
            if (str_starts_with($path, 'storage/')) return asset($path);
            if (str_starts_with($path, 'uploads/')) return asset('storage/' . $path);
            return asset('storage/' . $path);
        };

        $versionedAsset = static function (string $relative): string {
            $full = public_path($relative);
            $version = is_file($full) ? filemtime($full) : time();
            return asset($relative) . '?v=' . $version;
        };

        $favicon = $resolveStorageAsset($setting?->software_favicon ?? null, 'storage/icon/icon-padrao.webp');
        $canonical = $setting?->site_url ? rtrim($setting->site_url, '/') : url()->current();
    @endphp

    <link rel="shortcut icon" href="{{ $favicon }}" type="image/x-icon">
    <link rel="apple-touch-icon" href="{{ $favicon }}" type="image/x-icon">
    <link rel="manifest" href="{{ $versionedAsset('pixfacil-v15/manifest.webmanifest') }}">
    <meta name="theme-color" content="#050806">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $setting?->software_name ?: 'PixFácil' }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ $favicon }}">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title>{{ $setting?->software_name ?: config('app.name', 'PixFácil') }}</title>
    <meta name="description" content="{{ $setting?->meta_description }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $favicon }}">
    <meta name="keywords" content="{{ $setting?->meta_keywords }}">

    <meta property="og:title" content="{{ $setting?->og_title }}">
    <meta property="og:description" content="{{ $setting?->og_description }}">
    <meta property="og:image" content="{{ $favicon }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:site_name" content="{{ $setting?->software_name }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $setting?->twitter_title }}">
    <meta name="twitter:description" content="{{ $setting?->twitter_description }}">
    <meta name="twitter:image" content="{{ $favicon }}">

    <meta name="robots" content="{{ $setting?->allow_indexing ? 'index,follow' : 'noindex,nofollow' }}">
    <meta name="googlebot" content="{{ $setting?->allow_indexing ? 'index,follow' : 'noindex,nofollow' }}">
    <link rel="canonical" href="{{ $canonical }}">

    <link rel="stylesheet" href="{{ asset('assets/css/fontawesome.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Roboto+Condensed:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if (config('services.meta_capi.pixel_id'))
        <script>window.META_PIXEL_ID = @json(config('services.meta_capi.pixel_id'));</script>
    @endif

    @vite(['resources/js/app.js'])
    <link rel="stylesheet" href="{{ \Illuminate\Support\Facades\Vite::asset('style.css') }}">

    <style id="theme-color-tokens">{!! $themeCssVariables ?? ':root {}' !!}</style>
    <style>
        body{font-family:'Inter','Roboto Condensed',system-ui,-apple-system,BlinkMacSystemFont,sans-serif;background:var(--color-background,#020617);color:var(--color-text,#e5e7eb)}
        .navtop-color,:is(.dark .navtop-color),.bg-base,:is(.dark .bg-base),.theme-background{background-color:var(--color-background,#020617)}
        .theme-surface{background-color:var(--color-surface,#111827)}.theme-surface-2{background-color:var(--color-surface-2,#1f2937)}
        .theme-text{color:var(--color-text,#e5e7eb)}.theme-text-muted{color:var(--color-text-muted,#9ca3af)}.theme-primary{color:var(--color-primary,#facc15)}.theme-primary-bg{background-color:var(--color-primary,#facc15)}
        .theme-success{color:var(--color-success,#0ff77d)}.theme-warning{color:var(--color-warning,#f97316)}.theme-danger{color:var(--color-danger,#ef4444)}.theme-info{color:var(--color-info,#38bdf8)}.theme-accent{color:var(--color-accent,#a855f7)}
        .theme-border-primary{border-color:var(--color-primary,#facc15)}.theme-border-surface{border-color:var(--color-surface,#111827)}
    </style>

    @php
        $resolveThemeAsset = static function (?string $value, string $fallback) use ($resolveStorageAsset): string {
            if (! filled($value)) return asset($fallback);
            return $resolveStorageAsset($value, $fallback);
        };

        $pixfacilThemeLogo = $resolveThemeAsset(
            data_get($setting, 'pixfacil_mobile_logo') ?: data_get($setting, 'software_logo_white'),
            'pixfacil-v15/logo.svg'
        );

        $pixfacilLoadingLogo = $resolveThemeAsset(
            data_get($setting, 'pixfacil_loading_logo') ?: data_get($setting, 'software_logo_black') ?: data_get($setting, 'software_logo_white'),
            'pixfacil-v15/logo.svg'
        );

        $mobileBannerPath = null;
        try {
            $bannerQuery = \App\Models\Banner::query();
            if (\Illuminate\Support\Facades\Schema::hasColumn('banners', 'is_active')) {
                $bannerQuery->where('is_active', true);
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('banners', 'show_mobile')) {
                $bannerQuery->where('show_mobile', true);
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('banners', 'sort_order')) {
                $bannerQuery->orderBy('sort_order');
            } else {
                $bannerQuery->orderByDesc('updated_at');
            }
            $mobileBannerPath = $bannerQuery->value('image');
        } catch (\Throwable) {
            $mobileBannerPath = null;
        }

        $pixfacilThemeBanner = $resolveThemeAsset(
            $mobileBannerPath ?: data_get($setting, 'pixfacil_mobile_banner'),
            'pixfacil-v15/hero.webp'
        );

        $themeAssetVersion = max(
            is_file(public_path('pixfacil-v15/pixfacil-v15.js')) ? filemtime(public_path('pixfacil-v15/pixfacil-v15.js')) : 0,
            is_file(public_path('pixfacil-v15/pixfacil-desktop.js')) ? filemtime(public_path('pixfacil-v15/pixfacil-desktop.js')) : 0,
            is_file(public_path('pixfacil-v15/pixfacil-v15.css')) ? filemtime(public_path('pixfacil-v15/pixfacil-v15.css')) : 0,
            is_file(public_path('pixfacil-v15/pixfacil-desktop.css')) ? filemtime(public_path('pixfacil-v15/pixfacil-desktop.css')) : 0
        );

        $pixfacilMobileConfig = [
            'softwareName' => (string) ($setting?->software_name ?: 'PixFácil'),
            'pixfacilLogo' => $pixfacilThemeLogo,
            'pixfacilBanner' => $pixfacilThemeBanner,
            'logoLoading' => $pixfacilLoadingLogo,
            'liveEnabled' => (bool) ($setting?->custom?->live_ganhos_status ?? false),
            'content' => is_array($setting?->frontend_content ?? null) ? $setting->frontend_content : [],
            'assetVersion' => (string) $themeAssetVersion,
        ];

        $requestPath = '/' . trim(request()->path(), '/');
        if ($requestPath === '/') $requestPath = '/';

        $authRoute = (bool) preg_match('#^/(?:login|register|forget-password|forgot-password|reset-password)(?:/|$)#i', $requestPath);

        $ownedRoute =
            $requestPath === '/'
            || $authRoute
            || preg_match('#^/(?:casino|cassino|games|jogos|slots|live-casino|pesquisar|search)(?:/|$)#i', $requestPath)
            || preg_match('#^/profile/(?:account|deposit|withdraw|transactions|bets|affiliate|verification|responsible-gaming|identity|experience)(?:/|$)#i', $requestPath)
            || preg_match('#^/support-center(?:/|$)#i', $requestPath)
            || preg_match('#^/retro(?:/|$)#i', $requestPath)
            || preg_match('#^/(?:bonus|vip|missions|promotion|promotions|promocoes)(?:/|$)#i', $requestPath);

        $excludedRoute = preg_match('#^/admin(?:/|$)#i', $requestPath)
            || preg_match('#^/games/play(?:/|$)#i', $requestPath)
            || preg_match('#^/sport(?:book)?/play(?:/|$)#i', $requestPath);

        $ownedRoute = $ownedRoute && ! $excludedRoute;
        $gameRoute = preg_match('#^/games/play(?:/|$)#i', $requestPath)
            || preg_match('#^/sport(?:book)?/play(?:/|$)#i', $requestPath);
    @endphp

    <script>
        window.PIXFACIL_MOBILE_CONFIG = {!! \Illuminate\Support\Js::from($pixfacilMobileConfig) !!};
        window.PIXFACIL_V15_SERVER_OWNED = @json((bool) $ownedRoute);
        window.PIXFACIL_V13_GAME_ROUTE = @json((bool) $gameRoute);
    </script>

    <link rel="stylesheet" href="{{ $versionedAsset('pixfacil-v15/pixfacil-v15.css') }}">
    @if ($ownedRoute)
        <link rel="stylesheet" href="{{ $versionedAsset('pixfacil-v15/pixfacil-unified-pages.css') }}">
    @endif
    @if (! $excludedRoute)
        <link rel="stylesheet" href="{{ $versionedAsset('pixfacil-v15/pixfacil-desktop.css') }}">
    @endif
    <script defer src="{{ $versionedAsset('pixfacil-v15/pixfacil-v15.js') }}"></script>
    @if ($ownedRoute)
        <script defer src="{{ $versionedAsset('pixfacil-v15/pixfacil-content-sync.js') }}"></script>
    @endif
    @if (! $excludedRoute)
        <script defer src="{{ $versionedAsset('pixfacil-v15/pixfacil-desktop.js') }}"></script>
    @endif
</head>

<body color-theme="dark" class="bg-base{{ $ownedRoute ? ' pf15-owned-server' : '' }}{{ $gameRoute ? ' pf13-player-server' : '' }}">
    <div id="pixfacil-v15-app" aria-live="polite"></div>
    <div id="ondagamesv1"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.0.0/datepicker.min.js"></script>
    <script>
        window.Livewire?.on('copiado', (texto) => {
            navigator.clipboard.writeText(texto).then(() => Livewire.emit('copiado'));
        });

        window._token = '{{ csrf_token() }}';

        if (localStorage.getItem('color-theme') === 'light') {
            document.documentElement.classList.remove('dark');
            document.documentElement.classList.add('light');
        } else {
            document.documentElement.classList.remove('light');
            document.documentElement.classList.add('dark');
        }
    </script>

    @yield('content')
</body>
</html>
