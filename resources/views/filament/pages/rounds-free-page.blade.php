<x-filament::page>
    <style>
        .free-card{border-radius:20px;border:1px solid rgba(163, 163, 163,.18);background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.88));box-shadow:0 16px 48px rgba(0,0,0,.16);overflow:hidden}
        .free-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.14);background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.14),transparent 32%)}
        .free-title{margin:0;color:#fff;font-weight:900;font-size:22px;letter-spacing:-.03em}
        .free-sub{margin-top:6px;color:#d4d4d4;font-size:13px;line-height:1.45}
        .free-grid{display:grid;gap:10px;padding:14px 18px}
        @media(min-width:900px){.free-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .free-info{border:1px solid rgba(163, 163, 163,.14);border-radius:14px;background:rgba(10, 10, 10,.28);padding:12px}
        .free-info strong{display:block;color:#fff;font-size:12px}
        .free-info span{display:block;margin-top:4px;color:#a3a3a3;font-size:11px;line-height:1.4}
        .free-table{padding:0 0 6px;background:#fff}
        .dark .free-table{background:rgb(17 24 39)}
        .free-table .fi-ta-header{padding:14px 16px}
        .free-table .fi-ta-header-toolbar{gap:.5rem}
        .free-table .fi-ta-filters{padding:10px 14px 0!important}
        .free-table .fi-ta-filters form{gap:.75rem!important}
        .free-table .fi-fo-field-wrp{margin-bottom:0!important}
        .free-table .fi-fo-field-wrp-label span{font-size:11px!important;font-weight:700!important}
        .free-table .fi-input,.free-table .fi-select-input,.free-table input,.free-table select{min-height:38px!important}
        .free-table .fi-input-wrp,.free-table .fi-select{border-radius:12px!important}
        .free-table .fi-ta-table{font-size:13px}
        .free-table .fi-ta-table thead th{padding-top:.7rem!important;padding-bottom:.7rem!important}
        .free-table .fi-ta-table tbody td{padding-top:.65rem!important;padding-bottom:.65rem!important}
        .free-table .fi-ta-filters-trigger{min-height:36px!important}
    </style>

    <div class="space-y-5">
        <section class="free-card">
            <div class="free-head">
                <h2 class="free-title">Configuração de Rodadas Grátis</h2>
                <p class="free-sub">Defina jogo, depósito mínimo e quantidade padrão. A concessão manual fica na página “Dar Rodadas Grátis”.</p>
            </div>

            <div class="free-grid">
                <div class="free-info"><strong>Cliente padrão</strong><span>Use normalmente até 50 rodadas por cliente e até 200 clientes.</span></div>
                <div class="free-info"><strong>Volume liberado</strong><span>A PlayFiver pode aceitar até 1000 rodadas para contas com limite comercial liberado.</span></div>
                <div class="free-info"><strong>Página de configuração</strong><span>Esta área não envia rodadas. Ela só define regras e valores padrão.</span></div>
            </div>
        </section>

        <section class="free-card free-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>