<x-filament::page>
    <style>
        .pg-wrap{display:grid;gap:18px}
        .pg-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .pg-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .pg-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .pg-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .pg-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .pg-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.pg-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .pg-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .pg-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .pg-stat strong{display:block;margin-top:4px;color:#fff;font-size:17px;font-weight:950}
        .pg-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .pg-gateways{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.pg-gateways{grid-template-columns:repeat(5,minmax(0,1fr))}}
        .pg-gateway{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.25);padding:12px}
        .pg-gateway strong{display:flex;align-items:center;gap:8px;color:#fff;font-size:13px}
        .pg-dot{width:9px;height:9px;border-radius:99px;display:inline-block}
        .pg-ok{background:#22c55e}.pg-bad{background:#ef4444}
        .pg-gateway small{display:block;margin-top:7px;color:#a3a3a3;font-size:11px;word-break:break-all}
        .pg-form{padding:8px}
        .pg-form .fi-fo-tabs{border-radius:18px!important;overflow:hidden}
    </style>

    @php($stats = $this->stats())
    @php($gatewayStatus = $this->gatewayStatus())

    <div class="pg-wrap">
        <section class="pg-card">
            <div class="pg-hero">
                <h2 class="pg-title">Gateways de Pagamento</h2>
                <p class="pg-sub">Configure credenciais e tokens dos gateways PIX usados para depósitos e saques.</p>
                <div class="pg-note">
                    As credenciais aparecem preenchidas no formulário para conferência. Edite somente o que precisar alterar e clique em <strong>Salvar gateways</strong> no final da página.
                </div>
            </div>

            <div class="pg-stats">
                <div class="pg-stat"><span>Gateways configurados</span><strong>{{ $stats['configured'] }}/{{ $stats['total'] }}</strong><small>Com URL e credenciais</small></div>
                <div class="pg-stat"><span>Segurança</span><strong>PIN</strong><small>Confirmação no botão Salvar</small></div>
                <div class="pg-stat"><span>Última edição</span><strong style="font-size:12px">{{ $stats['last_update'] ? \Carbon\Carbon::parse($stats['last_update'])->format('d/m/Y H:i') : '-' }}</strong><small>gateways.updated_at</small></div>
            </div>

            <div class="pg-gateways">
                @foreach($gatewayStatus as $name => $gateway)
                    <div class="pg-gateway">
                        <strong><i class="pg-dot {{ $gateway['configured'] ? 'pg-ok' : 'pg-bad' }}"></i>{{ $name }}</strong>
                        <small>{{ $gateway['base_url'] }}</small>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="pg-card pg-form">
            {{ $this->form }}

            <div style="padding:16px 12px 10px;display:flex;justify-content:flex-end;border-top:1px solid rgba(163, 163, 163,.14);margin-top:12px">
                {{ $this->saveGatewaysAction }}
            </div>
        </section>

        <x-filament-actions::modals />
    </div>
</x-filament::page>