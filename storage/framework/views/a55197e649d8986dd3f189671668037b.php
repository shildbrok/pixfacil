<div class="wallet-info-modal">
    <style>
        .wallet-info-modal{max-height:70vh;overflow:auto;padding-right:2px}
        .wi-card{border:1px solid rgba(163, 163, 163,.22);border-radius:16px;background:rgba(20, 20, 20,.42);padding:14px}
        .wi-user{display:grid;gap:10px}
        @media(min-width:720px){.wi-user{grid-template-columns:1.15fr .85fr}}
        .wi-title{margin:0;color:#fff;font-size:16px;font-weight:950;line-height:1.2}
        .wi-sub{margin-top:4px;color:#a3a3a3;font-size:12px;line-height:1.45}
        .wi-pills{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}
        .wi-pill{border:1px solid rgba(163, 163, 163,.22);border-radius:999px;background:rgba(10, 10, 10,.35);padding:5px 8px;color:#d4d4d4;font-size:11px;font-weight:800}
        .wi-grid{display:grid;gap:10px;margin-top:12px}
        @media(min-width:520px){.wi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(min-width:880px){.wi-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .wi-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:14px;background:rgba(10, 10, 10,.28);padding:11px;min-height:72px}
        .wi-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.07em;font-weight:900}
        .wi-stat strong{display:block;margin-top:4px;color:#fff;font-size:15px;font-weight:950}
        .wi-stat small{display:block;margin-top:3px;color:#737373;font-size:10px}
        .wi-stat.primary{background:rgba(249, 115, 22,.09);border-color:rgba(251, 146, 60,.24)}
        .wi-stat.success{background:rgba(16,185,129,.08);border-color:rgba(52,211,153,.22)}
        .wi-stat.warning{background:rgba(245,158,11,.08);border-color:rgba(251,191,36,.22)}
        .wi-stat.danger{background:rgba(239,68,68,.08);border-color:rgba(248,113,113,.22)}
        .wi-section-title{margin:14px 0 8px;color:#e5e7eb;font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}
    </style>

    <div class="wi-card">
        <div class="wi-user">
            <div>
                <h3 class="wi-title"><?php echo e($wallet->user?->name ?: 'Usuário sem nome'); ?></h3>
                <div class="wi-sub"><?php echo e($wallet->user?->email ?: 'E-mail não informado'); ?></div>

                <div class="wi-pills">
                    <span class="wi-pill">CPF: <?php echo e($wallet->user?->cpf ?: '-'); ?></span>
                    <span class="wi-pill">Telefone: <?php echo e($wallet->user?->phone ?: '-'); ?></span>
                    <span class="wi-pill">Perfil: <?php echo e($wallet->user?->hasRole('admin') ? 'Admin' : 'Usuário'); ?></span>
                </div>
            </div>

            <div>
                <div class="wi-stat primary">
                    <span>Total apostável</span>
                    <strong><?php echo e($money($walletTotal)); ?></strong>
                    <small>Saque + depósito + bônus</small>
                </div>
            </div>
        </div>

        <div class="wi-pills">
            <span class="wi-pill">Cadastro: <?php echo e($wallet->user?->created_at?->format('d/m/Y H:i') ?: '-'); ?></span>
            <span class="wi-pill">Carteira atualizada: <?php echo e($wallet->updated_at?->format('d/m/Y H:i') ?: '-'); ?></span>
        </div>
    </div>

    <div class="wi-section-title">Saldos disponíveis</div>

    <div class="wi-grid">
        <div class="wi-stat success">
            <span>Carteira de saque</span>
            <strong><?php echo e($money($wallet->balance_withdrawal)); ?></strong>
            <small>balance_withdrawal</small>
        </div>

        <div class="wi-stat success">
            <span>Carteira de depósito</span>
            <strong><?php echo e($money($wallet->balance)); ?></strong>
            <small>balance</small>
        </div>

        <div class="wi-stat warning">
            <span>Carteira de bônus</span>
            <strong><?php echo e($money($wallet->balance_bonus)); ?></strong>
            <small>balance_bonus</small>
        </div>

        <div class="wi-stat danger">
            <span>Rollover total</span>
            <strong><?php echo e($money($rolloverTotal)); ?></strong>
            <small>Depósito + bônus</small>
        </div>

        <div class="wi-stat danger">
            <span>Rollover de depósito</span>
            <strong><?php echo e($money($wallet->balance_deposit_rollover)); ?></strong>
            <small>balance_deposit_rollover</small>
        </div>

        <div class="wi-stat danger">
            <span>Rollover de bônus</span>
            <strong><?php echo e($money($wallet->balance_bonus_rollover)); ?></strong>
            <small>balance_bonus_rollover</small>
        </div>

        <div class="wi-stat primary">
            <span>Comissão afiliado</span>
            <strong><?php echo e($money($wallet->refer_rewards)); ?></strong>
            <small>refer_rewards</small>
        </div>

        <div class="wi-stat">
            <span>Status da carteira</span>
            <strong><?php echo e($wallet->active ? 'Ativa' : 'Inativa'); ?></strong>
            <small>Controle operacional</small>
        </div>
    </div>
</div><?php /**PATH /home/u187586491/domains/pixfacil.fun/public_html/resources/views/filament/pages/partials/wallet-info-modal.blade.php ENDPATH**/ ?>