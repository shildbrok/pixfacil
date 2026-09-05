<?php if (isset($component)) { $__componentOriginalbe23554f7bded3778895289146189db7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe23554f7bded3778895289146189db7 = $attributes; } ?>
<?php $component = Filament\View\LegacyComponents\Page::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::page'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Filament\View\LegacyComponents\Page::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <?php ($stats = $this->getStats()); ?>
    <div class="grid gap-4 md:grid-cols-4 mb-5">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
            ['label' => 'Jogos importados', 'value' => $stats['total'], 'icon' => '🎮'],
            ['label' => 'Ativos', 'value' => $stats['active'], 'icon' => '🟢'],
            ['label' => 'Na Home', 'value' => $stats['home'], 'icon' => '🏠'],
            ['label' => 'Acessos', 'value' => number_format($stats['views'], 0, ',', '.'), 'icon' => '👁️'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="rounded-2xl border border-white/10 bg-black/30 p-4">
                <div class="text-xs text-gray-400"><?php echo e($card['icon']); ?> <?php echo e($card['label']); ?></div>
                <div class="mt-1 text-2xl font-bold text-white"><?php echo e($card['value']); ?></div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="mb-4 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm text-gray-300">
        <strong class="text-emerald-400">Motor separado do PlayFiver.</strong>
        Os jogos retrô usam tabelas próprias de catálogo e rodadas. Alterar esses parâmetros não muda providers, jogos ou sessões do cassino atual.
    </div>

    <?php echo e($this->table); ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe23554f7bded3778895289146189db7)): ?>
<?php $attributes = $__attributesOriginalbe23554f7bded3778895289146189db7; ?>
<?php unset($__attributesOriginalbe23554f7bded3778895289146189db7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe23554f7bded3778895289146189db7)): ?>
<?php $component = $__componentOriginalbe23554f7bded3778895289146189db7; ?>
<?php unset($__componentOriginalbe23554f7bded3778895289146189db7); ?>
<?php endif; ?>
<?php /**PATH /home/u187586491/domains/pixfacil.fun/public_html/resources/views/filament/pages/admin-retro-games-page.blade.php ENDPATH**/ ?>