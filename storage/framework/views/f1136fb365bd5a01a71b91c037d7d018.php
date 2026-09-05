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
</head>

<body color-theme="dark" class="bg-base">
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

</html><?php /**PATH /home/bulls777/htdocs/bulls777.bet/resources/views/layouts/app.blade.php ENDPATH**/ ?>