<x-filament::page>
    <style>
        .aw-wrap{display:grid;gap:18px}
        .aw-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .aw-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.14),transparent 32%)}
        .aw-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .aw-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .aw-note{margin-top:12px;border:1px solid rgba(52,211,153,.22);border-radius:16px;background:rgba(6,78,59,.18);padding:12px;color:#bbf7d0;font-size:12px;line-height:1.55}
        .aw-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.aw-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.aw-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .aw-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .aw-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .aw-stat strong{display:block;margin-top:4px;color:#fff;font-size:16px;font-weight:950}
        .aw-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .aw-stat.danger{border-color:rgba(248,113,113,.28);background:rgba(239,68,68,.10)}
        .aw-stat.warn{border-color:rgba(251,191,36,.28);background:rgba(245,158,11,.10)}
        .aw-table{padding:8px}
        .aw-table .fi-ta-header{padding:10px 12px}
        .aw-table .fi-ta-filters{padding:0 10px 10px!important}
        .aw-table .fi-input,.aw-table .fi-select-input,.aw-table input,.aw-table select{min-height:38px!important}
        .aw-table .fi-input-wrp,.aw-table .fi-select{border-radius:12px!important}
    </style>

    @php($stats = $this->stats())

    <div class="aw-wrap">
        <section class="aw-card">
            <div class="aw-hero">
                <h2 class="aw-title">Saques de Afiliados</h2>
                <p class="aw-sub">
                    Página própria para acompanhar, pagar, reembolsar e consultar saques solicitados por afiliados.
                </p>

                <div class="aw-note">
                    O reembolso retorna o valor para a carteira de comissão do afiliado: <strong>refer_rewards</strong>.
                    Excluir registro não paga, não reembolsa e não altera carteira.
                </div>
            </div>

            <div class="aw-stats">
                <div class="aw-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Registros</small></div>
                <div class="aw-stat warn"><span>Pendentes</span><strong>{{ $stats['pending'] }}</strong><small>{{ $this->money($stats['pending_amount']) }}</small></div>
                <div class="aw-stat"><span>Processando</span><strong>{{ $stats['processing'] }}</strong><small>Enviados ao gateway</small></div>
                <div class="aw-stat"><span>Aprovados</span><strong>{{ $stats['approved'] }}</strong><small>Pagos</small></div>
                <div class="aw-stat danger"><span>Cancelados</span><strong>{{ $stats['cancelled'] }}</strong><small>Reembolso/cancelamento</small></div>
                <div class="aw-stat"><span>Saques hoje</span><strong>{{ $this->money($stats['today_amount']) }}</strong><small>Solicitados hoje</small></div>
                <div class="aw-stat"><span>Pago hoje</span><strong>{{ $this->money($stats['approved_today']) }}</strong><small>Aprovados hoje</small></div>
                <div class="aw-stat"><span>Comissões</span><strong>{{ $this->money($stats['commission_balance']) }}</strong><small>Total refer_rewards</small></div>
            </div>
        </section>

        <section class="aw-card aw-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
