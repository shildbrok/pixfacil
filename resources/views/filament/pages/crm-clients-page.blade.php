<x-filament::page>

    <style>
        .crm-wrap{display:grid;gap:18px}
        .crm-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .crm-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.20),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.14),transparent 32%)}
        .crm-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.04em}
        .crm-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .crm-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.crm-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.crm-stats.crm-8{grid-template-columns:repeat(8,minmax(0,1fr))}.crm-stats.crm-6{grid-template-columns:repeat(6,minmax(0,1fr))}}
        .crm-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px}
        .crm-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .crm-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:900}
        .crm-grid{display:grid;gap:16px}
        @media(min-width:1100px){.crm-grid.two{grid-template-columns:1fr 1fr}.crm-grid.three{grid-template-columns:repeat(3,1fr)}}
        .crm-section{padding:16px 18px}
        .crm-section h3{margin:0 0 10px;color:#fff;font-size:16px;font-weight:900}
        .crm-table{padding:8px}
        .crm-table .fi-ta-header{padding:10px 12px}
        .crm-table .fi-ta-filters{padding:0 10px 10px!important}
        .crm-table .fi-input,.crm-table .fi-select-input,.crm-table input,.crm-table select{min-height:38px!important}
        .crm-table .fi-input-wrp,.crm-table .fi-select{border-radius:12px!important}
        .crm-table .fi-ta-table{font-size:13px}
        .crm-mini-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid rgba(163, 163, 163,.12);border-radius:14px;overflow:hidden}
        .crm-mini-table th{background:rgba(20, 20, 20,.75);color:#a3a3a3;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;padding:10px}
        .crm-mini-table td{border-top:1px solid rgba(163, 163, 163,.10);color:#ffedd5;font-size:12px;padding:10px}
        .crm-actions{display:flex;flex-wrap:wrap;gap:10px;padding:16px 18px}
    </style>

    <div class="crm-wrap">
        <section class="crm-card">
            <div class="crm-hero">
                <h2 class="crm-title">Clientes CRM</h2>
                <p class="crm-sub">Base completa de clientes com contato, CPF, depósitos, apostas, ganhos, perdas, saldo, afiliado, perfil e exportação.</p>
            </div>
            <div class="crm-stats crm-6">
                <div class="crm-stat"><span>Exportação</span><strong>CSV</strong></div>
                <div class="crm-stat"><span>Dados</span><strong>Contato</strong></div>
                <div class="crm-stat"><span>Métricas</span><strong>Apostas</strong></div>
                <div class="crm-stat"><span>Financeiro</span><strong>Depósitos</strong></div>
                <div class="crm-stat"><span>Perfil</span><strong>Auto</strong></div>
                <div class="crm-stat"><span>Filtros</span><strong>Avançados</strong></div>
            </div>
        </section>
        <section class="crm-card crm-table">{{ $this->table }}</section>
    </div>
</x-filament::page>
