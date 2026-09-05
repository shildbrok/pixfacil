<x-filament::page>
    <style>
        .aud-wrap{display:grid;gap:18px}
        .aud-card{border:1px solid rgba(163,163,163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20,20,20,.96),rgba(30,30,30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .aud-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(34,197,94,.16),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.12),transparent 32%)}
        .aud-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.04em}
        .aud-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .aud-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.aud-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .aud-stat{border:1px solid rgba(163,163,163,.14);border-radius:16px;background:rgba(10,10,10,.28);padding:13px}
        .aud-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .aud-stat strong{display:block;margin-top:4px;color:#fff;font-size:20px;font-weight:900}
        .aud-table{padding:8px}
        .aud-table .fi-ta-table{font-size:13px}
    </style>

    @php($stats = $this->getStats())

    <div class="aud-wrap">
        <section class="aud-card">
            <div class="aud-hero">
                <h2 class="aud-title">Auditoria de Ações Administrativas</h2>
                <p class="aud-sub">Registro imutável de ações sensíveis (edição de saldo, aprovação e reembolso de saque): quem fez, o que mudou, quando e de qual IP.</p>
            </div>

            <div class="aud-stats">
                <div class="aud-stat"><span>Total de registros</span><strong>{{ $stats['total'] }}</strong></div>
                <div class="aud-stat"><span>Hoje</span><strong>{{ $stats['today'] }}</strong></div>
                <div class="aud-stat"><span>Admins distintos</span><strong>{{ $stats['admins'] }}</strong></div>
                <div class="aud-stat"><span>Último registro</span><strong>{{ $stats['latest'] }}</strong></div>
            </div>
        </section>

        <section class="aud-card aud-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
