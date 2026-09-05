<x-filament::page>
    <style>
        .cpa-wrap{display:grid;gap:18px}
        .cpa-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .cpa-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(34,197,94,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.14),transparent 32%)}
        .cpa-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.04em}
        .cpa-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .cpa-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.cpa-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .cpa-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px}
        .cpa-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .cpa-stat strong{display:block;margin-top:4px;color:#fff;font-size:20px;font-weight:900}
        .cpa-table{padding:8px}
        .cpa-table .fi-ta-header{padding:10px 12px}
        .cpa-table .fi-ta-filters{padding:0 10px 10px!important}
        .cpa-table .fi-input,.cpa-table .fi-select-input,.cpa-table input,.cpa-table select{min-height:38px!important}
        .cpa-table .fi-input-wrp,.cpa-table .fi-select{border-radius:12px!important}
        .cpa-table .fi-ta-table{font-size:13px}
    </style>

    @php($stats = $this->getCpaStats())

    <div class="cpa-wrap">
        <section class="cpa-card">
            <div class="cpa-hero">
                <h2 class="cpa-title">Histórico de CPA Pago</h2>
                <p class="cpa-sub">
                    Consulte pagamentos de CPA, afiliado responsável, indicado qualificado e valor pago.
                </p>
            </div>

            <div class="cpa-stats">
                <div class="cpa-stat"><span>Registros</span><strong>{{ $stats['count'] }}</strong></div>
                <div class="cpa-stat"><span>Afiliados pagos</span><strong>{{ $stats['affiliates'] }}</strong></div>
                <div class="cpa-stat"><span>Indicados qualificados</span><strong>{{ $stats['indicated'] }}</strong></div>
                <div class="cpa-stat"><span>Total CPA pago</span><strong style="font-size:15px">R$ {{ number_format($stats['total_paid'], 2, ',', '.') }}</strong></div>
            </div>
        </section>

        <section class="cpa-card cpa-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
