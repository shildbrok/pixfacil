<x-filament::page>
    <style>
        .pf-wrap{display:grid;gap:18px}
        .pf-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .pf-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .pf-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .pf-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .pf-note{margin-top:12px;border:1px solid rgba(251,191,36,.22);border-radius:16px;background:rgba(120,53,15,.20);padding:12px;color:#fde68a;font-size:12px;line-height:1.55}
        .pf-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.pf-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.pf-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .pf-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .pf-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .pf-stat strong{display:block;margin-top:4px;color:#fff;font-size:16px;font-weight:950}
        .pf-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .pf-stat.danger{border-color:rgba(248,113,113,.28);background:rgba(239,68,68,.10)}
        .pf-table{padding:8px}
        .pf-table .fi-ta-header{padding:10px 12px}
        .pf-table .fi-ta-filters{padding:0 10px 10px!important}
        .pf-table .fi-input,.pf-table .fi-select-input,.pf-table input,.pf-table select{min-height:38px!important}
        .pf-table .fi-input-wrp,.pf-table .fi-select{border-radius:12px!important}
    </style>

    @php($stats = $this->getStats())

    <div class="pf-wrap">
        <section class="pf-card">
            <div class="pf-hero">
                <h2 class="pf-title">Carteiras PlayFiver</h2>
                <p class="pf-sub">
                    Tela informativa das carteiras retornadas pela PlayFiver. Aqui ninguém altera saldo, não cria carteira manual e não define carteira principal.
                </p>

                <div class="pf-note">
                    O saldo, a carteira principal e o tipo informativa/operacional são definidos pela sincronização com a PlayFiver.
                    A única edição permitida nesta tela é o <strong>sistema de notificação</strong>.
                </div>
            </div>

            <div class="pf-stats">
                <div class="pf-stat"><span>Carteiras</span><strong>{{ $stats['count'] }}</strong><small>PlayFiver</small></div>
                <div class="pf-stat"><span>Saldo total</span><strong>{{ $this->money($stats['total']) }}</strong><small>Todas as carteiras</small></div>
                <div class="pf-stat"><span>Operacional</span><strong>{{ $this->money($stats['operational']) }}</strong><small>info_only = não</small></div>
                <div class="pf-stat"><span>Informativo</span><strong>{{ $this->money($stats['informative']) }}</strong><small>info_only = sim</small></div>
                <div class="pf-stat {{ $stats['primary_blocking'] ? 'danger' : '' }}"><span>Principal</span><strong>{{ $this->money($stats['primary_balance']) }}</strong><small>{{ $stats['primary_name'] }}</small></div>
                <div class="pf-stat"><span>Abaixo alerta</span><strong>{{ $stats['below_notify'] }}</strong><small>Notificação ativa</small></div>
                <div class="pf-stat"><span>Com erro</span><strong>{{ $stats['with_error'] }}</strong><small>Último erro registrado</small></div>
                <div class="pf-stat"><span>Última sync</span><strong style="font-size:12px">{{ $stats['last_sync'] ? \Carbon\Carbon::parse($stats['last_sync'])->format('d/m/Y H:i') : '-' }}</strong><small>PlayFiver</small></div>
            </div>
        </section>

        <section class="pf-card pf-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>