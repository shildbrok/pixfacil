<?php
    $setting = \Helper::getSetting();

    $resolveStorageAsset = function (?string $path) {
        if (blank($path)) {
            return null;
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

    $logoWhite = $resolveStorageAsset($setting?->software_logo_white ?? null);
    $logoBlack = $resolveStorageAsset($setting?->software_logo_black ?? null);
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($setting): ?>
    <div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoWhite): ?>
            <img src="<?php echo e($logoWhite); ?>" alt="" class="show-in-dark h-8">
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoBlack): ?>
            <img src="<?php echo e($logoBlack); ?>" alt="" class="show-in-light h-8">
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH /home/u187586491/domains/pixfacil.fun/public_html/resources/views/filament/components/logo.blade.php ENDPATH**/ ?>