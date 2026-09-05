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
        .gm-wrap{display:grid;gap:18px}
        .gm-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .gm-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .gm-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .gm-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .gm-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .gm-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.gm-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1400px){.gm-stats{grid-template-columns:repeat(11,minmax(0,1fr))}}
        .gm-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .gm-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .gm-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .gm-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .gm-table{padding:10px}
        .gm-modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(10, 10, 10,.80);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .gm-modal{width:min(920px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(163, 163, 163,.20);border-radius:24px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45)}
        .gm-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .gm-modal-head h3{margin:0;color:#fff;font-size:18px;font-weight:950}
        .gm-modal-head p{margin:4px 0 0;color:#a3a3a3;font-size:12px}
        .gm-close{border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:rgba(20, 20, 20,.85);color:#fff;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer}
        .gm-modal-body{padding:18px;display:grid;gap:14px}
        .gm-preview{border:1px solid rgba(163, 163, 163,.16);border-radius:20px;background:#0a0a0a;overflow:hidden}
        .gm-preview-img{height:320px;background:#0a0a0a;display:flex;align-items:center;justify-content:center}
        .gm-preview-img img{width:100%;height:100%;object-fit:contain}
        .gm-preview-body{padding:16px;display:grid;gap:8px}
        .gm-preview-title{color:#fff;font-size:20px;font-weight:950}
        .gm-preview-desc{color:#d4d4d4;font-size:13px;line-height:1.55}
        .gm-pills{display:flex;gap:8px;flex-wrap:wrap}
        .gm-pill{display:inline-flex;width:max-content;border-radius:999px;background:rgba(249, 115, 22,.14);color:#fdba74;border:1px solid rgba(249, 115, 22,.24);padding:4px 10px;font-size:11px;font-weight:900}
        .gm-pill-ok{background:rgba(34,197,94,.14);color:#22c55e;border-color:rgba(34,197,94,.24)}
        .gm-pill-bad{background:rgba(239,68,68,.14);color:#ef4444;border-color:rgba(239,68,68,.24)}
        .gm-info-grid{display:grid;gap:10px}
        @media(min-width:800px){.gm-info-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .gm-info{border:1px solid rgba(163, 163, 163,.14);border-radius:14px;background:rgba(10, 10, 10,.30);padding:12px}
        .gm-info span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .gm-info strong{display:block;margin-top:4px;color:#fff;font-size:13px;word-break:break-all}
    </style>

    <?php ($stats = $this->stats()); ?>

    <div class="gm-wrap">
        <section class="gm-card">
            <div class="gm-hero">
                <h2 class="gm-title">Jogos</h2>
                <p class="gm-sub">
                    Gerencie o catálogo local, status, home, destaque, capas, códigos PlayFiver e categorias vinculadas.
                </p>

                <div class="gm-note">
                    As categorias são salvas na tabela <strong>category_game</strong>. Ao editar jogos, a página limpa caches do catálogo e renova <strong>asset_version</strong>.
                </div>
            </div>

            <div class="gm-stats">
                <div class="gm-stat"><span>Total</span><strong><?php echo e($stats['total']); ?></strong><small>Jogos</small></div>
                <div class="gm-stat"><span>Ativos</span><strong><?php echo e($stats['active']); ?></strong><small>Disponíveis</small></div>
                <div class="gm-stat"><span>Inativos</span><strong><?php echo e($stats['inactive']); ?></strong><small>Ocultos</small></div>
                <div class="gm-stat"><span>Home</span><strong><?php echo e($stats['home']); ?></strong><small>show_home</small></div>
                <div class="gm-stat"><span>Destaque</span><strong><?php echo e($stats['featured']); ?></strong><small>is_featured</small></div>
                <div class="gm-stat"><span>Originais</span><strong><?php echo e($stats['original']); ?></strong><small>original</small></div>
                <div class="gm-stat"><span>Sem categoria</span><strong><?php echo e($stats['without_category']); ?></strong><small>Revisar</small></div>
                <div class="gm-stat"><span>Sem capa</span><strong><?php echo e($stats['without_cover']); ?></strong><small>Visual</small></div>
                <div class="gm-stat"><span>Provedores</span><strong><?php echo e($stats['providers']); ?></strong><small>providers</small></div>
                <div class="gm-stat"><span>Vínculos</span><strong><?php echo e($stats['category_links']); ?></strong><small>category_game</small></div>
                <div class="gm-stat"><span>Última edição</span><strong style="font-size:12px"><?php echo e($stats['last_update'] ? \Carbon\Carbon::parse($stats['last_update'])->format('d/m/Y H:i') : '-'); ?></strong><small>games.updated_at</small></div>
            </div>
        </section>

        <section class="gm-card gm-table">
            <?php echo e($this->table); ?>

        </section>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showPreviewModal && $previewGame): ?>
        <div class="gm-modal-backdrop" wire:click.self="closePreview">
            <div class="gm-modal">
                <div class="gm-modal-head">
                    <div>
                        <h3><?php echo e($previewGame->game_name); ?></h3>
                        <p><?php echo e($previewGame->provider?->name ?: 'Sem provedor'); ?></p>
                    </div>
                    <button type="button" class="gm-close" wire:click="closePreview">Fechar</button>
                </div>

                <div class="gm-modal-body">
                    <div class="gm-preview">
                        <div class="gm-preview-img">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->imageUrl($previewGame->cover)): ?>
                                <img src="<?php echo e($this->imageUrl($previewGame->cover)); ?>" alt="<?php echo e($previewGame->game_name); ?>">
                            <?php else: ?>
                                <span style="color:#737373;font-size:12px">Sem capa</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div class="gm-preview-body">
                            <div class="gm-preview-title"><?php echo e($previewGame->game_name); ?></div>
                            <div class="gm-preview-desc">
                                <?php echo e($previewGame->description ?: 'Jogo do catálogo local.'); ?>

                            </div>

                            <div class="gm-pills">
                                <span class="gm-pill <?php echo e($previewGame->status ? 'gm-pill-ok' : 'gm-pill-bad'); ?>"><?php echo e($previewGame->status ? 'Ativo' : 'Inativo'); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($previewGame->show_home): ?><span class="gm-pill gm-pill-ok">Home</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($previewGame->is_featured): ?><span class="gm-pill gm-pill-ok">Destaque</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($previewGame->original): ?><span class="gm-pill">Original</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $previewGame->categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="gm-pill"><?php echo e($category->name); ?></span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="gm-info-grid">
                        <div class="gm-info"><span>Game ID</span><strong><?php echo e($previewGame->game_id ?: '—'); ?></strong></div>
                        <div class="gm-info"><span>Game Code</span><strong><?php echo e($previewGame->game_code ?: '—'); ?></strong></div>
                        <div class="gm-info"><span>Distribuição</span><strong><?php echo e($previewGame->distribution ?: '—'); ?></strong></div>
                        <div class="gm-info"><span>Views</span><strong><?php echo e(number_format((int) $previewGame->views, 0, ',', '.')); ?></strong></div>
                        <div class="gm-info"><span>RTP</span><strong><?php echo e($previewGame->rtp !== null ? $previewGame->rtp . '%' : '—'); ?></strong></div>
                        <div class="gm-info"><span>Atualizado</span><strong><?php echo e($previewGame->updated_at?->format('d/m/Y H:i') ?: '—'); ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH /home/u187586491/domains/pixfacil.fun/public_html/resources/views/filament/pages/admin-games-page.blade.php ENDPATH**/ ?>