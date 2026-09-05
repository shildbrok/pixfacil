<x-filament::page>
    <style>
        .kyc-wrap{display:grid;gap:18px}
        .kyc-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .kyc-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(239,68,68,.13),transparent 32%)}
        .kyc-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .kyc-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .kyc-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .kyc-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.kyc-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.kyc-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .kyc-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .kyc-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .kyc-stat strong{display:block;margin-top:4px;color:#fff;font-size:17px;font-weight:950}
        .kyc-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .kyc-dot{display:inline-block;width:9px;height:9px;border-radius:99px;margin-right:7px}
        .kyc-ok{background:#22c55e}.kyc-bad{background:#ef4444}.kyc-warn{background:#f59e0b}
        .kyc-form{padding:8px}
        .kyc-form .fi-fo-tabs{border-radius:18px!important;overflow:hidden}
    </style>

    @php($stats = $this->stats())

    <div class="kyc-wrap">
        <section class="kyc-card">
            <div class="kyc-hero">
                <h2 class="kyc-title">KYC e Compliance</h2>
                <p class="kyc-sub">
                    Configure as regras de verificação de identidade usadas em saques e controle o comportamento dos documentos enviados pelos clientes.
                </p>

                <div class="kyc-note">
                    Esta tela controla a tabela <strong>kyc_configs</strong>. Os pedidos de verificação continuam sendo avaliados na página de verificações.
                </div>
            </div>

            <div class="kyc-stats">
                <div class="kyc-stat">
                    <span>Configuração</span>
                    <strong><i class="kyc-dot {{ $stats['active'] ? 'kyc-ok' : 'kyc-bad' }}"></i>{{ $stats['active'] ? 'Ativa' : 'Inativa' }}</strong>
                    <small>Regra atual</small>
                </div>

                <div class="kyc-stat">
                    <span>Saque</span>
                    <strong><i class="kyc-dot {{ $stats['withdrawal_required'] ? 'kyc-warn' : 'kyc-ok' }}"></i>{{ $stats['withdrawal_required'] ? 'Exige KYC' : 'Livre' }}</strong>
                    <small>Regra de saque</small>
                </div>

                <div class="kyc-stat">
                    <span>Autoaprovação</span>
                    <strong>{{ $stats['auto_approve'] ? 'Ativa' : 'Inativa' }}</strong>
                    <small>Documentos novos</small>
                </div>

                <div class="kyc-stat"><span>Pendentes</span><strong>{{ $stats['pending'] }}</strong><small>Aguardando análise</small></div>
                <div class="kyc-stat"><span>Aprovados</span><strong>{{ $stats['approved'] }}</strong><small>Histórico</small></div>
                <div class="kyc-stat"><span>Rejeitados</span><strong>{{ $stats['rejected'] }}</strong><small>Histórico</small></div>
                <div class="kyc-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Verificações</small></div>
                <div class="kyc-stat"><span>Atualizado</span><strong style="font-size:12px">{{ $stats['updated_at'] ? \Carbon\Carbon::parse($stats['updated_at'])->format('d/m/Y H:i') : '-' }}</strong><small>kyc_configs</small></div>
            </div>
        </section>

        <section class="kyc-card kyc-form">
            {{ $this->form }}

            <div style="padding:16px 12px 10px;display:flex;justify-content:flex-end;border-top:1px solid rgba(163, 163, 163,.14);margin-top:12px">
                {{ $this->saveKycSettingsAction }}
            </div>
        </section>

        <x-filament-actions::modals />
    </div>
</x-filament::page>
