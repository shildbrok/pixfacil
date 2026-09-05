<x-filament::page>
    <style>
        .wd-wrap{display:grid;gap:18px}
        .wd-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .wd-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(251,191,36,.14),transparent 32%)}
        .wd-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .wd-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .wd-note{margin-top:12px;border:1px solid rgba(251,191,36,.22);border-radius:16px;background:rgba(120,53,15,.20);padding:12px;color:#fde68a;font-size:12px;line-height:1.55}
        .wd-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.wd-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.wd-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .wd-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .wd-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .wd-stat strong{display:block;margin-top:4px;color:#fff;font-size:16px;font-weight:950}
        .wd-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .wd-stat.danger{border-color:rgba(248,113,113,.28);background:rgba(239,68,68,.10)}
        .wd-stat.warn{border-color:rgba(251,191,36,.28);background:rgba(245,158,11,.10)}
        .wd-table{padding:8px}
        .wd-table .fi-ta-header{padding:10px 12px}
        .wd-table .fi-ta-filters{padding:0 10px 10px!important}
        .wd-table .fi-input,.wd-table .fi-select-input,.wd-table input,.wd-table select{min-height:38px!important}
        .wd-table .fi-input-wrp,.wd-table .fi-select{border-radius:12px!important}
    </style>

    @php($stats = $this->stats())

    <div class="wd-wrap">
        <section class="wd-card">
            <div class="wd-hero">
                <h2 class="wd-title">Saques de Usuários</h2>
                <p class="wd-sub">
                    Página própria para acompanhar, pagar, reembolsar e consultar saques. O pagamento continua usando o gateway configurado no sistema.
                </p>

                <div class="wd-note">
                    Ações sensíveis continuam exigindo PIN administrativo. Reembolso retorna o valor para a carteira de saque do usuário.
                </div>
            </div>

            <div class="wd-stats">
                <div class="wd-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Registros</small></div>
                <div class="wd-stat warn"><span>Pendentes</span><strong>{{ $stats['pending'] }}</strong><small>{{ $this->money($stats['pending_amount']) }}</small></div>
                <div class="wd-stat"><span>Processando</span><strong>{{ $stats['processing'] }}</strong><small>Enviados ao gateway</small></div>
                <div class="wd-stat"><span>Aprovados</span><strong>{{ $stats['approved'] }}</strong><small>Pagos</small></div>
                <div class="wd-stat danger"><span>Cancelados</span><strong>{{ $stats['cancelled'] }}</strong><small>Reembolso/cancelamento</small></div>
                <div class="wd-stat"><span>Saques hoje</span><strong>{{ $this->money($stats['today_amount']) }}</strong><small>Solicitados hoje</small></div>
                <div class="wd-stat"><span>Pago hoje</span><strong>{{ $this->money($stats['approved_today']) }}</strong><small>Aprovados hoje</small></div>
                <div class="wd-stat"><span>Fila</span><strong>{{ $stats['pending'] + $stats['processing'] }}</strong><small>Pendente + processando</small></div>
            </div>
        </section>

        <section class="wd-card wd-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
