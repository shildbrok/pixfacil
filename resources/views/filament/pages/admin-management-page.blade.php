<x-filament::page>
    <style>
        .adm-wrap{display:grid;gap:18px}
        .adm-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .adm-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.22),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.15),transparent 32%)}
        .adm-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.04em}
        .adm-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .adm-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.adm-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .adm-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px}
        .adm-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .adm-stat strong{display:block;margin-top:4px;color:#fff;font-size:20px;font-weight:900}
        .adm-table{padding:8px}
        .adm-table .fi-ta-header{padding:10px 12px}
        .adm-table .fi-ta-filters{padding:0 10px 10px!important}
        .adm-table .fi-input,.adm-table .fi-select-input,.adm-table input,.adm-table select{min-height:38px!important}
        .adm-table .fi-input-wrp,.adm-table .fi-select{border-radius:12px!important}
        .adm-table .fi-ta-table{font-size:13px}
    </style>

    @php($stats = $this->getAdminStats())

    <div class="adm-wrap">
        <section class="adm-card">
            <div class="adm-hero">
                <h2 class="adm-title">Gerenciar Admins</h2>
                <p class="adm-sub">
                    Cadastre, edite e controle administradores com acesso ao painel. A permissão usa a role <strong>admin</strong>.
                </p>
            </div>

            <div class="adm-stats">
                <div class="adm-stat"><span>Total admins</span><strong>{{ $stats['total'] }}</strong></div>
                <div class="adm-stat"><span>Ativos</span><strong>{{ $stats['active'] }}</strong></div>
                <div class="adm-stat"><span>Inativos</span><strong>{{ $stats['inactive'] }}</strong></div>
                <div class="adm-stat"><span>Criados hoje</span><strong>{{ $stats['created_today'] }}</strong></div>
            </div>
        </section>

        <section class="adm-card adm-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
