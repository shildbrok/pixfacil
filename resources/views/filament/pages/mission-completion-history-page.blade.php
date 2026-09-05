<x-filament::page>
    <style>
        .hist-wrap{display:grid;gap:18px}
        .hist-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .hist-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.14),transparent 32%)}
        .hist-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.04em}
        .hist-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .hist-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.hist-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .hist-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px}
        .hist-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .hist-stat strong{display:block;margin-top:4px;color:#fff;font-size:20px;font-weight:900}
        .hist-table{padding:8px}
        .hist-table .fi-ta-header{padding:10px 12px}
        .hist-table .fi-ta-filters{padding:0 10px 10px!important}
        .hist-table .fi-input,.hist-table .fi-select-input,.hist-table input,.hist-table select{min-height:38px!important}
        .hist-table .fi-input-wrp,.hist-table .fi-select{border-radius:12px!important}
        .hist-table .fi-ta-table{font-size:13px}
    </style>

    @php($stats = $this->getStats())

    <div class="hist-wrap">
        <section class="hist-card">
            <div class="hist-hero">
                <h2 class="hist-title">Histórico de Missões Completas</h2>
                <p class="hist-sub">Acompanhe progresso, resgate e recompensa de missões por usuário.</p>
            </div>

            <div class="hist-stats">
                <div class="hist-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong></div>
                <div class="hist-stat"><span>Pago/Ativo</span><strong>{{ $stats['paid'] ?? $stats['today'] ?? $stats['active'] ?? $stats['redeemed'] ?? $stats['claimed'] ?? $stats['bets'] ?? 0 }}</strong></div>
                <div class="hist-stat"><span>Pendente/Outros</span><strong>{{ $stats['pending'] ?? $stats['closed'] ?? $stats['users'] ?? $stats['wins'] ?? 0 }}</strong></div>
                <div class="hist-stat"><span>Valor/Data</span><strong style="font-size:14px">{{ isset($stats['amount']) ? 'R$ ' . number_format($stats['amount'], 2, ',', '.') : (isset($stats['rewards']) ? 'R$ ' . number_format($stats['rewards'], 2, ',', '.') : ($stats['latest'] ?? ($stats['expired'] ?? '-'))) }}</strong></div>
            </div>
        </section>

        <section class="hist-card hist-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
