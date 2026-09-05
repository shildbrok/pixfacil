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
        .crm-wrap{display:grid;gap:18px}
        .crm-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .crm-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.20),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.14),transparent 32%)}
        .crm-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .crm-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .crm-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.crm-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.crm-stats{grid-template-columns:repeat(6,minmax(0,1fr))}.crm-stats.big{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .crm-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px;min-height:92px}
        .crm-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .crm-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .crm-stat small{display:block;margin-top:5px;color:#a3a3a3;font-size:11px;line-height:1.35}
        .crm-grid{display:grid;gap:16px}
        @media(min-width:1150px){.crm-grid.two{grid-template-columns:1fr 1fr}.crm-grid.three{grid-template-columns:repeat(3,1fr)}}
        .crm-section{padding:16px 18px}
        .crm-section h3{margin:0 0 10px;color:#fff;font-size:16px;font-weight:900}
        .crm-mini-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid rgba(163, 163, 163,.12);border-radius:14px;overflow:hidden}
        .crm-mini-table th{background:rgba(20, 20, 20,.75);color:#a3a3a3;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;padding:10px}
        .crm-mini-table td{border-top:1px solid rgba(163, 163, 163,.10);color:#ffedd5;font-size:12px;padding:10px;vertical-align:top}
        .crm-bars{display:grid;gap:10px}
        .crm-bar-row{display:grid;grid-template-columns:62px 1fr 92px;gap:10px;align-items:center;color:#d4d4d4;font-size:12px}
        .crm-bar-bg{height:10px;border-radius:999px;background:rgba(163, 163, 163,.18);overflow:hidden}
        .crm-bar-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#fb923c,#22c55e)}
    </style>

    <?php ($stats = $this->stats()); ?>
    <?php ($distribution = $this->depositDistribution()); ?>
    <?php ($flow = $this->recentFlow()); ?>
    <?php ($maxFlow = max(1, collect($flow)->max(fn($row) => max($row['deposits'], $row['withdrawals'], $row['bets'], $row['wins'])))); ?>

    <div class="crm-wrap">
        <section class="crm-card">
            <div class="crm-hero">
                <h2 class="crm-title">Dashboard CRM da Casa</h2>
                <p class="crm-sub">
                    Indicadores operacionais, financeiro, jogadores online, PlayFiver, afiliados, conversão e distribuição de depósitos.
                </p>
            </div>

            <div class="crm-stats big">
                <div class="crm-stat">
                    <span>Total de depósitos</span>
                    <strong><?php echo e($this->money($stats['total_deposits'])); ?></strong>
                    <small><?php echo e($stats['total_deposit_count']); ?> depósitos pagos</small>
                </div>

                <div class="crm-stat">
                    <span>Total de saques</span>
                    <strong><?php echo e($this->money($stats['total_withdrawals'])); ?></strong>
                    <small><?php echo e($stats['total_withdrawal_count']); ?> solicitações</small>
                </div>

                <div class="crm-stat">
                    <span>Players jogando agora</span>
                    <strong><?php echo e($stats['players_now']); ?></strong>
                    <small>Jogadores ativos últimos 5 min</small>
                </div>

                <div class="crm-stat">
                    <span>Total de perdas</span>
                    <strong><?php echo e($this->money($stats['bets_today'])); ?></strong>
                    <small>Apostas hoje</small>
                </div>

                <div class="crm-stat">
                    <span>Total de ganhos</span>
                    <strong><?php echo e($this->money($stats['wins_today'])); ?></strong>
                    <small>Ganhos pagos hoje</small>
                </div>

                <div class="crm-stat">
                    <span>Lucro da casa</span>
                    <strong><?php echo e($this->money($stats['house_profit_today'])); ?></strong>
                    <small>Hoje</small>
                </div>

                <div class="crm-stat">
                    <span>Usuários</span>
                    <strong><?php echo e($stats['users_total']); ?></strong>
                    <small>Total cadastrados</small>
                </div>

                <div class="crm-stat">
                    <span>Afiliados</span>
                    <strong><?php echo e($this->money($stats['affiliate_payable'])); ?></strong>
                    <small>Ganhos a pagar</small>
                </div>
            </div>
        </section>

        <section class="crm-card">
            <div class="crm-section">
                <h3>Hoje vs ontem</h3>
            </div>
            <div class="crm-stats">
                <div class="crm-stat">
                    <span>Depósitos hoje</span>
                    <strong><?php echo e($this->money($stats['deposits_today'])); ?></strong>
                    <small>vs ontem: <?php echo e($stats['deposits_vs_yesterday']); ?></small>
                </div>

                <div class="crm-stat">
                    <span>Saques hoje</span>
                    <strong><?php echo e($this->money($stats['withdrawals_today'])); ?></strong>
                    <small>vs ontem: <?php echo e($stats['withdrawals_vs_yesterday']); ?></small>
                </div>

                <div class="crm-stat">
                    <span>Fluxo líquido hoje</span>
                    <strong><?php echo e($this->money($stats['net_flow_today'])); ?></strong>
                    <small>vs ontem: <?php echo e($stats['net_flow_vs_yesterday']); ?></small>
                </div>

                <div class="crm-stat">
                    <span>Conversão novos</span>
                    <strong><?php echo e($this->pct($stats['new_conversion'])); ?></strong>
                    <small><?php echo e($stats['new_conversion_label']); ?></small>
                </div>

                <div class="crm-stat">
                    <span>Ticket médio hoje</span>
                    <strong><?php echo e($this->money($stats['avg_ticket_today'])); ?></strong>
                    <small><?php echo e($stats['deposit_count_today']); ?> depósito(s)</small>
                </div>

                <div class="crm-stat">
                    <span>Novos hoje</span>
                    <strong><?php echo e($stats['new_today']); ?></strong>
                    <small>Cadastros do dia</small>
                </div>
            </div>
        </section>

        <section class="crm-card">
            <div class="crm-section">
                <h3>Saldos</h3>
            </div>
            <div class="crm-stats">
                <div class="crm-stat">
                    <span>Saldo players</span>
                    <strong><?php echo e($this->money($stats['player_balance'])); ?></strong>
                    <small>Somatório das carteiras dos usuários</small>
                </div>

                <div class="crm-stat">
                    <span>Saldo PlayFiver</span>
                    <strong><?php echo e($this->money($stats['playfiver_total'])); ?></strong>
                    <small><?php echo e($stats['playfiver_count']); ?> carteiras • Operacional: <?php echo e($this->money($stats['playfiver_operational'])); ?> • Informativo: <?php echo e($this->money($stats['playfiver_info'])); ?></small>
                </div>

                <div class="crm-stat">
                    <span>Carteira Principal PF</span>
                    <strong><?php echo e($this->money($stats['playfiver_primary'])); ?></strong>
                    <small>Saldo da carteira principal da PlayFiver</small>
                </div>
            </div>
        </section>

        <div class="crm-grid two">
            <section class="crm-card crm-section">
                <h3>Distribuição de usuários por depósitos</h3>
                <div class="crm-stats" style="padding:0;grid-template-columns:repeat(2,minmax(0,1fr))">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $distribution; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="crm-stat">
                            <span><?php echo e($label); ?></span>
                            <strong><?php echo e($count); ?></strong>
                            <small>Distribuição de usuários</small>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </section>

            <section class="crm-card crm-section">
                <h3>Fluxo dos últimos 7 dias</h3>
                <div class="crm-bars">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $flow; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="crm-bar-row">
                            <div><?php echo e($row['date']); ?></div>
                            <div class="crm-bar-bg"><div class="crm-bar-fill" style="width: <?php echo e($this->barWidth($row['deposits'], $maxFlow)); ?>%"></div></div>
                            <div><?php echo e($this->money($row['deposits'])); ?></div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <small style="display:block;margin-top:10px;color:#a3a3a3">Barras representam depósitos pagos por dia.</small>
            </section>
        </div>

        <section class="crm-card crm-section">
            <h3>Provedores reais com melhor resultado</h3>
            <table class="crm-mini-table">
                <thead>
                    <tr>
                        <th>Agregador</th>
                        <th>Provedor real</th>
                        <th>Eventos</th>
                        <th>Apostado</th>
                        <th>Ganho</th>
                        <th>Lucro casa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->providerResume(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($row['aggregator']); ?></td>
                            <td><strong><?php echo e($row['provider_code']); ?></strong><br><small><?php echo e($row['provider_name']); ?></small></td>
                            <td><?php echo e($row['events']); ?></td>
                            <td><?php echo e($this->money($row['total_bet'])); ?></td>
                            <td><?php echo e($this->money($row['total_win'])); ?></td>
                            <td><?php echo e($this->money($row['house_result'])); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
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
<?php /**PATH /home/u187586491/domains/pixfacil.fun/public_html/resources/views/filament/pages/crm-dashboard-page.blade.php ENDPATH**/ ?>