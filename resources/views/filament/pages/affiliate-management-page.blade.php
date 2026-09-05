<x-filament::page>
    <style>
        .aff-wrap{display:grid;gap:18px}
        .aff-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .aff-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .aff-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.04em}
        .aff-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .aff-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.aff-stats{grid-template-columns:repeat(5,minmax(0,1fr))}}
        .aff-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px}
        .aff-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .aff-stat strong{display:block;margin-top:4px;color:#fff;font-size:20px;font-weight:900}
        .aff-table{padding:8px}
        .aff-table .fi-ta-header{padding:10px 12px}
        .aff-table .fi-ta-filters{padding:0 10px 10px!important}
        .aff-table .fi-input,.aff-table .fi-select-input,.aff-table input,.aff-table select{min-height:38px!important}
        .aff-table .fi-input-wrp,.aff-table .fi-select{border-radius:12px!important}
        .aff-table .fi-ta-table{font-size:13px}
    </style>

    @php($stats = $this->getAffiliateStats())

    <div class="aff-wrap">
        <section class="aff-card">
            <div class="aff-hero">
                <h2 class="aff-title">Gestão de Afiliados</h2>
                <p class="aff-sub">
                    Acompanhe afiliados, indicados, depositantes, CPA pago e lucro gerado. Use “Configurar” para definir CPA % e depósito mínimo por afiliado.
                </p>
            </div>

            <div class="aff-stats">
                <div class="aff-stat"><span>Afiliados</span><strong>{{ $stats['affiliates'] }}</strong></div>
                <div class="aff-stat"><span>Indicados</span><strong>{{ $stats['referrals'] }}</strong></div>
                <div class="aff-stat"><span>Depositantes</span><strong>{{ $stats['depositors'] }}</strong></div>
                <div class="aff-stat"><span>Lucro gerado</span><strong style="font-size:15px">R$ {{ number_format($stats['total_deposited'], 2, ',', '.') }}</strong></div>
                <div class="aff-stat"><span>CPA pago</span><strong style="font-size:15px">R$ {{ number_format($stats['total_cpa_paid'], 2, ',', '.') }}</strong></div>
            </div>
        </section>

        <section class="aff-card aff-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>