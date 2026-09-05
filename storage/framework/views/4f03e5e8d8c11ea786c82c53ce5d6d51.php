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
    <style>
        .users-wrap{display:grid;gap:18px}
        .users-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .users-hero{padding:18px 20px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .users-title{margin:0;color:#fff;font-size:24px;font-weight:950;letter-spacing:-.04em}
        .users-sub{margin:6px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .users-stats{display:grid;gap:10px;padding:0 20px 16px}
        @media(min-width:900px){.users-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(min-width:1300px){.users-stats{grid-template-columns:repeat(6,minmax(0,1fr))}}
        .users-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:74px}
        .users-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .users-stat strong{display:block;margin-top:3px;color:#fff;font-size:18px;font-weight:950}
        .users-stat small{display:block;margin-top:2px;color:#a3a3a3;font-size:11px}
        .users-table{padding:8px}
        .users-table .fi-ta-header{padding:10px 12px}
        .users-table .fi-ta-filters{padding:0 10px 10px!important}
        .users-table .fi-input,.users-table .fi-select-input,.users-table input,.users-table select{min-height:38px!important}
        .users-table .fi-input-wrp,.users-table .fi-select{border-radius:12px!important}
    </style>

    <?php ($stats = $this->getUserStats()); ?>

    <div class="users-wrap">
        <section class="users-card">
            <div class="users-hero">
                <h2 class="users-title">Usuários</h2>
                <p class="users-sub">
                    Página própria, sem Resource. Listagem objetiva com botões para <strong>Informações</strong>, <strong>Editar</strong> e <strong>Senha</strong>.
                </p>
            </div>

            <div class="users-stats">
                <div class="users-stat"><span>Total</span><strong><?php echo e($stats['total']); ?></strong><small>Usuários</small></div>
                <div class="users-stat"><span>Depositantes</span><strong><?php echo e($stats['depositors']); ?></strong><small>Com depósito pago</small></div>
                <div class="users-stat"><span>Apostadores</span><strong><?php echo e($stats['bettors']); ?></strong><small>Com apostas</small></div>
                <div class="users-stat"><span>Influencers</span><strong><?php echo e($stats['influencers']); ?></strong><small>Modo publicidade</small></div>
                <div class="users-stat"><span>Saldo players</span><strong style="font-size:13px">R$ <?php echo e(number_format($stats['wallet_total'], 2, ',', '.')); ?></strong><small>Carteiras</small></div>
                <div class="users-stat"><span>Depositado</span><strong style="font-size:13px">R$ <?php echo e(number_format($stats['total_deposited'], 2, ',', '.')); ?></strong><small>Total pago</small></div>
            </div>
        </section>

        <section class="users-card users-table">
            <?php echo e($this->table); ?>

        </section>
    </div>
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
<?php /**PATH /home/u187586491/domains/pixfacil.fun/public_html/resources/views/filament/pages/admin-users-page.blade.php ENDPATH**/ ?>