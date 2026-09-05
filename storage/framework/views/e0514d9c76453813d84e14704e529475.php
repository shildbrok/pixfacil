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
    
    <div wire:poll.15s style="display:flex;flex-direction:column;gap:1.25rem">

        
        <?php if (isset($component)) { $__componentOriginalee08b1367eba38734199cf7829b1d1e9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee08b1367eba38734199cf7829b1d1e9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.section.index','data' => ['icon' => 'heroicon-o-signal']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'heroicon-o-signal']); ?>
             <?php $__env->slot('heading', null, []); ?> Está rodando agora? <?php $__env->endSlot(); ?>
             <?php $__env->slot('description', null, []); ?> 
                Atualiza sozinho a cada 15 segundos. O agendador carimba um horário toda vez
                que roda; o status abaixo vem da idade desse carimbo.
             <?php $__env->endSlot(); ?>

            <?php
                $fg = match ($scheduler['color']) {
                    'success' => 'rgb(34,197,94)',
                    'warning' => 'rgb(202,138,4)',
                    default   => 'rgb(239,68,68)',
                };
                $bg = match ($scheduler['color']) {
                    'success' => 'rgba(34,197,94,.12)',
                    'warning' => 'rgba(234,179,8,.14)',
                    default   => 'rgba(239,68,68,.12)',
                };
            ?>

            <div style="border-radius:.75rem;padding:1.15rem 1.25rem;background:<?php echo e($bg); ?>;border:1px solid <?php echo e($fg); ?>33;max-width:420px">
                <div style="font-size:.8rem;font-weight:600;color:var(--gray-500)">Agendador (cron)</div>
                <div style="display:flex;align-items:center;gap:.55rem;margin-top:.4rem">
                    <span style="width:.7rem;height:.7rem;border-radius:999px;background:<?php echo e($fg); ?>;box-shadow:0 0 0 4px <?php echo e($fg); ?>22"></span>
                    <span style="font-size:1.3rem;font-weight:700;color:<?php echo e($fg); ?>"><?php echo e($scheduler['label']); ?></span>
                </div>
                <div style="font-size:.8rem;color:var(--gray-500);margin-top:.5rem">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scheduler['last']): ?>
                        Última execução: <?php echo e($scheduler['last']->diffForHumans()); ?>

                    <?php else: ?>
                        Nunca rodou — falta registrar o cron abaixo.
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div style="font-size:.74rem;color:var(--gray-400);margin-top:.15rem">
                    Roda todas as tarefas: sessões de jogo, saldo do agregador e o sistema de distribuição.
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scheduler['state'] === 'ok'): ?>
                <div style="margin-top:1rem;font-size:.85rem;color:rgb(34,197,94);font-weight:600">
                    ✓ Tudo certo. Nenhum worker é necessário — o agendador faz tudo.
                </div>
            <?php else: ?>
                <div style="margin-top:1rem;border-radius:.6rem;padding:.8rem 1rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);font-size:.84rem;color:rgb(220,38,38)">
                    <strong>O agendador não está rodando.</strong>
                    Registre o cron job abaixo no seu painel de hospedagem (CloudPanel → Cron Jobs).
                    Enquanto isso, sessões de jogo não fecham, o saldo do agregador não sincroniza e o
                    sistema de distribuição fica parado.
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee08b1367eba38734199cf7829b1d1e9)): ?>
<?php $attributes = $__attributesOriginalee08b1367eba38734199cf7829b1d1e9; ?>
<?php unset($__attributesOriginalee08b1367eba38734199cf7829b1d1e9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee08b1367eba38734199cf7829b1d1e9)): ?>
<?php $component = $__componentOriginalee08b1367eba38734199cf7829b1d1e9; ?>
<?php unset($__componentOriginalee08b1367eba38734199cf7829b1d1e9); ?>
<?php endif; ?>

        
        <?php if (isset($component)) { $__componentOriginalee08b1367eba38734199cf7829b1d1e9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee08b1367eba38734199cf7829b1d1e9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.section.index','data' => ['icon' => 'heroicon-o-clipboard-document-list']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'heroicon-o-clipboard-document-list']); ?>
             <?php $__env->slot('heading', null, []); ?> Como registrar no CloudPanel <?php $__env->endSlot(); ?>
             <?php $__env->slot('description', null, []); ?> 
                É <strong>um cron job só</strong>. Em <em>Sites → seu site → Cron Jobs → Novo Cron Job</em>,
                preencha assim:
             <?php $__env->endSlot(); ?>

            
            <div style="font-size:.82rem;font-weight:600;margin-bottom:.5rem">1. Horário — deixe tudo a cada minuto</div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.35rem">
                <div style="border:1px solid var(--gray-200);border-radius:.5rem;padding:.4rem .7rem;background:var(--gray-50)">
                    <span style="font-size:.7rem;color:var(--gray-500)">Modelo</span>
                    <div style="font-weight:700;font-size:.85rem">Todo minuto</div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['Minuto','Hora','Dia','Mês','Dia da semana']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div style="border:1px solid var(--gray-200);border-radius:.5rem;padding:.4rem .7rem;background:var(--gray-50);text-align:center;min-width:74px">
                        <span style="font-size:.7rem;color:var(--gray-500)"><?php echo e($campo); ?></span>
                        <div style="font-weight:700;font-size:1rem;font-family:monospace">*</div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div style="font-size:.74rem;color:var(--gray-400);margin-bottom:1rem">
                Selecionar o modelo “Todo minuto” já preenche os cinco campos com <code>*</code>.
            </div>

            
            <div style="font-size:.82rem;font-weight:600;margin-bottom:.35rem">2. Comando — cole exatamente isto</div>
            <div x-data="{ copied: false, cmd: <?php echo \Illuminate\Support\Js::from($schedulerCommand)->toHtml() ?> }" style="position:relative">
                <pre style="overflow-x:auto;background:var(--gray-950,#0a0a0a);color:#e5e5e5;border-radius:.6rem;padding:.85rem 1rem;font-size:.78rem;line-height:1.5;margin:0;white-space:pre-wrap;word-break:break-all"><?php echo e($schedulerCommand); ?></pre>
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText(cmd); copied = true; setTimeout(() => copied = false, 1500)"
                    style="position:absolute;top:.5rem;right:.5rem;font-size:.72rem;padding:.25rem .6rem;border-radius:.4rem;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;cursor:pointer"
                >
                    <span x-show="!copied">Copiar</span>
                    <span x-show="copied" x-cloak>Copiado!</span>
                </button>
            </div>

            <div style="margin-top:.9rem;font-size:.78rem;color:var(--gray-500)">
                Depois é só <strong>Adicionar Cron Job</strong>. Em até 1 minuto o status lá em cima fica verde.
                O PHP usado é <code><?php echo e($phpBinary); ?></code> — se o seu servidor usar outra versão, ajuste o número.
            </div>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee08b1367eba38734199cf7829b1d1e9)): ?>
<?php $attributes = $__attributesOriginalee08b1367eba38734199cf7829b1d1e9; ?>
<?php unset($__attributesOriginalee08b1367eba38734199cf7829b1d1e9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee08b1367eba38734199cf7829b1d1e9)): ?>
<?php $component = $__componentOriginalee08b1367eba38734199cf7829b1d1e9; ?>
<?php unset($__componentOriginalee08b1367eba38734199cf7829b1d1e9); ?>
<?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($queue['pending'] > 0 || $queue['failed'] > 0): ?>
            <?php if (isset($component)) { $__componentOriginalee08b1367eba38734199cf7829b1d1e9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee08b1367eba38734199cf7829b1d1e9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.section.index','data' => ['icon' => 'heroicon-o-exclamation-triangle']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'heroicon-o-exclamation-triangle']); ?>
                 <?php $__env->slot('heading', null, []); ?> Atenção na fila <?php $__env->endSlot(); ?>
                <div style="font-size:.85rem;color:var(--gray-600)">
                    Normalmente esta seção nem aparece — nada é enfileirado nesta configuração.
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($queue['pending'] > 0): ?>
                        Há <strong><?php echo e($queue['pending']); ?></strong> job(s) parado(s) na fila
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($queue['oldest_age'] !== null): ?>
                            (o mais antigo há <?php echo e(\Carbon\CarbonInterval::seconds($queue['oldest_age'])->cascade()->forHumans(short: true)); ?>)
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        — sinal de que algum código voltou a usar a fila sem worker.
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($queue['failed'] > 0): ?>
                        <strong><?php echo e($queue['failed']); ?></strong> job(s) falharam.
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee08b1367eba38734199cf7829b1d1e9)): ?>
<?php $attributes = $__attributesOriginalee08b1367eba38734199cf7829b1d1e9; ?>
<?php unset($__attributesOriginalee08b1367eba38734199cf7829b1d1e9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee08b1367eba38734199cf7829b1d1e9)): ?>
<?php $component = $__componentOriginalee08b1367eba38734199cf7829b1d1e9; ?>
<?php unset($__componentOriginalee08b1367eba38734199cf7829b1d1e9); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div>
            <?php if (isset($component)) { $__componentOriginal6330f08526bbb3ce2a0da37da512a11f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.button.index','data' => ['wire:click' => 'refreshStatus','icon' => 'heroicon-o-arrow-path','color' => 'gray']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => 'refreshStatus','icon' => 'heroicon-o-arrow-path','color' => 'gray']); ?>
                Atualizar agora
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6330f08526bbb3ce2a0da37da512a11f)): ?>
<?php $attributes = $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f; ?>
<?php unset($__attributesOriginal6330f08526bbb3ce2a0da37da512a11f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6330f08526bbb3ce2a0da37da512a11f)): ?>
<?php $component = $__componentOriginal6330f08526bbb3ce2a0da37da512a11f; ?>
<?php unset($__componentOriginal6330f08526bbb3ce2a0da37da512a11f); ?>
<?php endif; ?>
        </div>
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
<?php /**PATH /home/u187586491/domains/pixfacil.fun/public_html/resources/views/filament/pages/admin-system-jobs-page.blade.php ENDPATH**/ ?>