<x-filament::page>
    <style>
        .wallet-wrap{display:grid;gap:18px}
        .wallet-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .wallet-hero{padding:18px 20px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .wallet-title{margin:0;color:#fff;font-size:24px;font-weight:950;letter-spacing:-.04em}
        .wallet-sub{margin:6px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .wallet-stats{display:grid;gap:10px;padding:0 20px 16px}
        @media(min-width:900px){.wallet-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.wallet-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .wallet-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:74px}
        .wallet-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .wallet-stat strong{display:block;margin-top:3px;color:#fff;font-size:18px;font-weight:950}
        .wallet-stat small{display:block;margin-top:2px;color:#a3a3a3;font-size:11px}
        .wallet-table{padding:8px}
        .wallet-table .fi-ta-header{padding:10px 12px}
        .wallet-table .fi-ta-filters{padding:0 10px 10px!important}
        .wallet-table .fi-input,.wallet-table .fi-select-input,.wallet-table input,.wallet-table select{min-height:38px!important}
        .wallet-table .fi-input-wrp,.wallet-table .fi-select{border-radius:12px!important}
    </style>

    @php($stats = $this->getWalletStats())

    <div class="wallet-wrap">
        <section class="wallet-card">
            <div class="wallet-hero">
                <h2 class="wallet-title">Carteiras</h2>
                <p class="wallet-sub">
                    Página própria para consultar e corrigir saldos, rollover e comissão de afiliado. Sem Resource antigo.
                </p>
            </div>

            <div class="wallet-stats">
                <div class="wallet-stat"><span>Carteiras</span><strong>{{ $stats['wallets'] }}</strong><small>{{ $stats['active'] }} ativas</small></div>
                <div class="wallet-stat"><span>Total apostável</span><strong style="font-size:13px">{{ $this->money($stats['total_available']) }}</strong><small>Saque + depósito + bônus</small></div>
                <div class="wallet-stat"><span>Saque</span><strong style="font-size:13px">{{ $this->money($stats['balance_withdrawal']) }}</strong><small>balance_withdrawal</small></div>
                <div class="wallet-stat"><span>Depósito</span><strong style="font-size:13px">{{ $this->money($stats['balance']) }}</strong><small>balance</small></div>
                <div class="wallet-stat"><span>Bônus</span><strong style="font-size:13px">{{ $this->money($stats['balance_bonus']) }}</strong><small>balance_bonus</small></div>
                <div class="wallet-stat"><span>Rollover total</span><strong style="font-size:13px">{{ $this->money($stats['rollover_total']) }}</strong><small>Depósito + bônus</small></div>
                <div class="wallet-stat"><span>Rollover depósito</span><strong style="font-size:13px">{{ $this->money($stats['rollover_deposit']) }}</strong><small>balance_deposit_rollover</small></div>
                <div class="wallet-stat"><span>Afiliados</span><strong style="font-size:13px">{{ $this->money($stats['affiliate']) }}</strong><small>refer_rewards</small></div>
            </div>
        </section>

        <section class="wallet-card wallet-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>