<x-filament::page>
    <style>
        .bettor-wrap{display:grid;gap:18px}
        .bettor-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .bettor-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(245,158,11,.16),transparent 32%)}
        .bettor-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.04em}
        .bettor-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .bettor-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.bettor-stats{grid-template-columns:repeat(6,minmax(0,1fr))}}
        .bettor-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px}
        .bettor-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .bettor-stat strong{display:block;margin-top:4px;color:#fff;font-size:20px;font-weight:900}
        .bettor-table{padding:8px}
        .bettor-table .fi-ta-header{padding:10px 12px}
        .bettor-table .fi-ta-filters{padding:0 10px 10px!important}
        .bettor-table .fi-input,.bettor-table .fi-select-input,.bettor-table input,.bettor-table select{min-height:38px!important}
        .bettor-table .fi-input-wrp,.bettor-table .fi-select{border-radius:12px!important}
        .bettor-table .fi-ta-table{font-size:13px}
    </style>

    @php($stats = $this->getBettorStats())

    <div class="bettor-wrap">
        <section class="bettor-card">
            <div class="bettor-hero">
                <h2 class="bettor-title">Gestão de Apostadores</h2>
                <p class="bettor-sub">
                    Analise volume apostado, ganhos, perdas, depósitos e frequência de atividade dos jogadores.
                </p>
            </div>

            <div class="bettor-stats">
                <div class="bettor-stat"><span>Apostadores</span><strong>{{ $stats['bettors'] }}</strong></div>
                <div class="bettor-stat"><span>Ativos hoje</span><strong>{{ $stats['active_today'] }}</strong></div>
                <div class="bettor-stat"><span>Apostado</span><strong style="font-size:14px">R$ {{ number_format($stats['total_bet'], 2, ',', '.') }}</strong></div>
                <div class="bettor-stat"><span>Ganho</span><strong style="font-size:14px">R$ {{ number_format($stats['total_win'], 2, ',', '.') }}</strong></div>
                <div class="bettor-stat"><span>Perda</span><strong style="font-size:14px">R$ {{ number_format($stats['total_loss'], 2, ',', '.') }}</strong></div>
                <div class="bettor-stat"><span>Depositado</span><strong style="font-size:14px">R$ {{ number_format($stats['total_deposited'], 2, ',', '.') }}</strong></div>
            </div>
        </section>

        <section class="bettor-card bettor-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
