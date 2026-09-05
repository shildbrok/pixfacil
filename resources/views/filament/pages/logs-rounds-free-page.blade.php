<x-filament::page>
    <style>
        .frh-wrap{display:grid;gap:18px}
        .frh-card{border-radius:22px;border:1px solid rgba(163, 163, 163,.18);overflow:hidden;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18)}
        .frh-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.14),transparent 32%)}
        .frh-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.04em}
        .frh-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .frh-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.frh-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .frh-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px}
        .frh-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .frh-stat strong{display:block;margin-top:4px;color:#fff;font-size:21px;font-weight:900}
        .frh-table{padding:8px}
        .frh-table .fi-ta-header{padding:10px 12px}
        .frh-table .fi-ta-filters{padding:0 10px 10px!important}
        .frh-table .fi-ta-filters form{gap:.75rem!important}
        .frh-table .fi-input,.frh-table .fi-select-input,.frh-table input,.frh-table select{min-height:38px!important}
        .frh-table .fi-input-wrp,.frh-table .fi-select{border-radius:12px!important}
        .frh-table .fi-ta-table{font-size:13px}
        .frh-table .fi-ta-table thead th{padding-top:.7rem!important;padding-bottom:.7rem!important}
        .frh-table .fi-ta-table tbody td{padding-top:.7rem!important;padding-bottom:.7rem!important}
    </style>

    @php($stats = $this->getHistoryStats())

    <div class="frh-wrap">
        <section class="frh-card">
            <div class="frh-hero">
                <h2 class="frh-title">Histórico de Rodadas Grátis</h2>
                <p class="frh-sub">
                    Acompanhe os registros de envio e processamento de rodadas grátis com uma leitura mais limpa e organizada.
                </p>
            </div>

            <div class="frh-stats">
                <div class="frh-stat">
                    <span>Total de registros</span>
                    <strong>{{ $stats['total'] }}</strong>
                </div>
                <div class="frh-stat">
                    <span>Sucesso</span>
                    <strong>{{ $stats['success'] }}</strong>
                </div>
                <div class="frh-stat">
                    <span>Falhou</span>
                    <strong>{{ $stats['failed'] }}</strong>
                </div>
                <div class="frh-stat">
                    <span>Último registro</span>
                    <strong style="font-size:15px">{{ $stats['latest'] }}</strong>
                </div>
            </div>
        </section>

        <section class="frh-card frh-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>