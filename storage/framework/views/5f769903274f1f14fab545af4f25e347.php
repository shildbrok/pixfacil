<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="dark">

<head>
    <?php
        $setting = \Helper::getSetting();

        $resolveStorageAsset = function (?string $path, string $fallback) {
            if (blank($path)) {
                return asset($fallback);
            }

            $path = ltrim((string) $path, '/');

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            if (str_starts_with($path, 'storage/')) {
                return asset($path);
            }

            if (str_starts_with($path, 'uploads/')) {
                return asset('storage/' . $path);
            }

            return asset('storage/' . $path);
        };

        $favicon = $resolveStorageAsset($setting?->software_favicon ?? null, 'storage/icon/icon-padrao.webp');

        $canonical = $setting?->site_url
                ? rtrim($setting->site_url, '/')
                : url()->current();
    ?>

    <link rel="shortcut icon" href="<?php echo e($favicon); ?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?php echo e($favicon); ?>" type="image/x-icon">
    <link rel="manifest" href="<?php echo e(asset('pixfacil-v15/manifest.webmanifest')); ?>?v=20260904-1531">
    <meta name="theme-color" content="#050806">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PixFácil">
    <link rel="apple-touch-icon" sizes="192x192" href="<?php echo e(asset('pixfacil-v15/icons/icon-192.png')); ?>">

    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title><?php echo e($setting->software_name); ?></title>
    <meta name="description" content="<?php echo e($setting->meta_description); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo e($favicon); ?>">
    <meta name="keywords" content="<?php echo e($setting->meta_keywords); ?>">

    <meta property="og:title" content="<?php echo e($setting->og_title); ?>">
    <meta property="og:description" content="<?php echo e($setting->og_description); ?>">
    <meta property="og:image" content="<?php echo e($favicon); ?>">
    <meta property="og:url" content="<?php echo e($canonical); ?>">
    <meta property="og:site_name" content="<?php echo e($setting->software_name); ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo e($setting->twitter_title); ?>">
    <meta name="twitter:description" content="<?php echo e($setting->twitter_description); ?>">
    <meta name="twitter:image" content="<?php echo e($favicon); ?>">

    <meta name="robots" content="<?php echo e($setting->allow_indexing ? 'index,follow' : 'noindex,nofollow'); ?>">
    <meta name="googlebot" content="<?php echo e($setting->allow_indexing ? 'index,follow' : 'noindex,nofollow'); ?>">

    <link rel="canonical" href="<?php echo e($canonical); ?>">

    <link rel="stylesheet" href="<?php echo e(asset('assets/css/fontawesome.min.css')); ?>">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto+Condensed:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('services.meta_capi.pixel_id')): ?>
        <script>
            window.META_PIXEL_ID = "<?php echo e(config('services.meta_capi.pixel_id')); ?>";
        </script>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/app.js']); ?>
    <link rel="stylesheet" href="<?php echo e(\Illuminate\Support\Facades\Vite::asset('style.css')); ?>">

    <style id="theme-color-tokens">
        <?php echo $themeCssVariables ?? ':root {}'; ?>

    </style>

    <style>
        body {
            font-family: 'Inter', 'Roboto Condensed', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--color-background, #020617);
            color: var(--color-text, #e5e7eb);
        }

        .navtop-color {
            background-color: var(--color-background, #020617);
        }

        :is(.dark .navtop-color) {
            background-color: var(--color-background, #020617);
        }

        .bg-base {
            background-color: var(--color-background, #020617);
        }

        :is(.dark .bg-base) {
            background-color: var(--color-background, #020617);
        }

        .theme-background {
            background-color: var(--color-background, #020617);
        }

        .theme-surface {
            background-color: var(--color-surface, #111827);
        }

        .theme-surface-2 {
            background-color: var(--color-surface-2, #1f2937);
        }

        .theme-text {
            color: var(--color-text, #e5e7eb);
        }

        .theme-text-muted {
            color: var(--color-text-muted, #9ca3af);
        }

        .theme-primary {
            color: var(--color-primary, #facc15);
        }

        .theme-primary-bg {
            background-color: var(--color-primary, #facc15);
        }

        .theme-success {
            color: var(--color-success, #0ff77d);
        }

        .theme-warning {
            color: var(--color-warning, #f97316);
        }

        .theme-danger {
            color: var(--color-danger, #ef4444);
        }

        .theme-info {
            color: var(--color-info, #38bdf8);
        }

        .theme-accent {
            color: var(--color-accent, #a855f7);
        }

        .theme-border-primary {
            border-color: var(--color-primary, #facc15);
        }

        .theme-border-surface {
            border-color: var(--color-surface, #111827);
        }
    </style>

    
    <?php
        $resolvePf9Asset = static function (?string $value, string $fallback) use ($resolveStorageAsset): string {
            if (!filled($value)) {
                return asset($fallback);
            }

            $raw = ltrim((string) $value, '/');

            if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
                return $raw;
            }

            $publicRelative = str_starts_with($raw, 'storage/')
                ? $raw
                : (str_starts_with($raw, 'uploads/') ? 'storage/' . $raw : 'storage/' . $raw);

            if (!is_file(public_path($publicRelative))) {
                return asset($fallback);
            }

            return asset($publicRelative);
        };

        // Estes campos passam a existir após a migration da V9.
        // Enquanto ela ainda não foi executada, o fallback do tema funciona normalmente.
        $pixfacilThemeLogo = $resolvePf9Asset(
            data_get($setting, 'pixfacil_mobile_logo'),
            'pixfacil-v15/logo.svg'
        );

        $pixfacilThemeBanner = $resolvePf9Asset(
            data_get($setting, 'pixfacil_mobile_banner'),
            'pixfacil-v15/hero.webp'
        );

        $pixfacilLoadingLogo = $resolvePf9Asset(
            data_get($setting, 'pixfacil_loading_logo'),
            'pixfacil-v15/logo.svg'
        );

        $pixfacilMobileConfig = [
            'softwareName' => 'PixFácil',
            'pixfacilLogo' => $pixfacilThemeLogo,
            'pixfacilBanner' => $pixfacilThemeBanner,
            'logoLoading' => $pixfacilLoadingLogo,
            'liveEnabled' => (bool) ($setting?->custom?->live_ganhos_status ?? false),
        ];

        $pf9RequestPath = '/' . trim(request()->path(), '/');
        if ($pf9RequestPath === '/') {
            $pf9RequestPath = '/';
        }

        $pf9OwnedRoute =
            $pf9RequestPath === '/'
            || preg_match('#^/(?:login|register|forget-password|forgot-password|reset-password)(?:/|$)#i', $pf9RequestPath)
            || preg_match('#^/(?:casino|pesquisar|search)(?:/|$)#i', $pf9RequestPath)
            || preg_match('#^/profile/(?:account|deposit|withdraw|transactions|bets|affiliate|verification|responsible-gaming|identity|experience)(?:/|$)#i', $pf9RequestPath)
            || preg_match('#^/support-center(?:/|$)#i', $pf9RequestPath)
            || preg_match('#^/retro(?:/|$)#i', $pf9RequestPath)
            || preg_match('#^/(?:bonus|vip|promotion|promotions|promocoes)(?:/|$)#i', $pf9RequestPath);

        $pf9ExcludedRoute =
            preg_match('#^/admin(?:/|$)#i', $pf9RequestPath)
            || preg_match('#^/games/play(?:/|$)#i', $pf9RequestPath)
            || preg_match('#^/sport(?:book)?/play(?:/|$)#i', $pf9RequestPath);

        $pf9OwnedRoute = $pf9OwnedRoute && !$pf9ExcludedRoute;

        $pf13GameRoute =
            preg_match('#^/games/play(?:/|$)#i', $pf9RequestPath)
            || preg_match('#^/sport(?:book)?/play(?:/|$)#i', $pf9RequestPath);
    ?>

    <script>
        window.PIXFACIL_MOBILE_CONFIG = <?php echo \Illuminate\Support\Js::from($pixfacilMobileConfig); ?>;
        window.PIXFACIL_V15_SERVER_OWNED = <?php echo json_encode((bool) $pf9OwnedRoute, 15, 512) ?>;
        window.PIXFACIL_V13_GAME_ROUTE = <?php echo json_encode((bool) $pf13GameRoute, 15, 512) ?>;
    </script>
    <link rel="stylesheet" href="<?php echo e(asset('pixfacil-v15/pixfacil-v15.css')); ?>?v=20260904-1531">
    <script defer src="<?php echo e(asset('pixfacil-v15/pixfacil-v15.js')); ?>?v=20260904-1531"></script>

</head>

<body color-theme="dark" class="bg-base<?php echo e($pf9OwnedRoute ? ' pf15-owned-server' : ''); ?><?php echo e($pf13GameRoute ? ' pf13-player-server' : ''); ?>">
    <div id="pixfacil-v15-app" aria-live="polite"></div>
    <div id="ondagamesv1"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.0.0/datepicker.min.js"></script>
    <script>
        window.Livewire?.on('copiado', (texto) => {
            navigator.clipboard.writeText(texto).then(() => {
                Livewire.emit('copiado');
            });
        });

        window._token = '<?php echo e(csrf_token()); ?>';

        if (localStorage.getItem('color-theme') === 'light') {
            document.documentElement.classList.remove('dark');
            document.documentElement.classList.add('light');
        } else {
            document.documentElement.classList.remove('light');
            document.documentElement.classList.add('dark');
        }
    </script>

    <?php echo $__env->yieldContent('content'); ?>
</body>

</html><?php /**PATH /home/u187586491/domains/pixfacil.fun/public_html/resources/views/layouts/app.blade.php ENDPATH**/ ?>