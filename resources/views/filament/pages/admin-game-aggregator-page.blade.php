<x-filament::page>
    <style>
        .ga-wrap{display:grid;gap:18px}
        .ga-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .ga-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.13),transparent 32%)}
        .ga-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .ga-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .ga-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .ga-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.ga-stats{grid-template-columns:repeat(5,minmax(0,1fr))}}
        .ga-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .ga-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .ga-stat strong{display:block;margin-top:4px;color:#fff;font-size:17px;font-weight:950}
        .ga-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px;word-break:break-all}
        .ga-dot{display:inline-block;width:9px;height:9px;border-radius:99px;margin-right:7px}
        .ga-ok{background:#22c55e}.ga-bad{background:#ef4444}
        .ga-form{padding:8px}
        .ga-form .fi-fo-tabs{border-radius:18px!important;overflow:hidden}
    </style>

    @php($stats = $this->stats())

    <div class="ga-wrap">
        <section class="ga-card">
            <div class="ga-hero">
                <h2 class="ga-title">Agregador de Jogos</h2>
                <p class="ga-sub">
                    Configure a integração PlayFiver usada para abertura de jogos, webhooks, catálogo, carteiras e rodadas grátis.
                </p>

                <div class="ga-note">
                    As credenciais ficam visíveis para conferência. Para salvar alterações, use o botão <strong>Salvar agregador</strong> no final da página e confirme com PIN administrativo.
                </div>
            </div>

            <div class="ga-stats">
                <div class="ga-stat">
                    <span>Status</span>
                    <strong><i class="ga-dot {{ $stats['configured'] ? 'ga-ok' : 'ga-bad' }}"></i>{{ $stats['configured'] ? 'Configurado' : 'Incompleto' }}</strong>
                    <small>Código, token e secret</small>
                </div>

                <div class="ga-stat"><span>API Base</span><strong style="font-size:12px">{{ $stats['api_url'] }}</strong><small>PlayFiver endpoint</small></div>
                <div class="ga-stat"><span>Provedores</span><strong>{{ $stats['providers'] }}</strong><small>No catálogo local</small></div>
                <div class="ga-stat"><span>Jogos</span><strong>{{ $stats['games'] }}</strong><small>No catálogo local</small></div>
                <div class="ga-stat"><span>Última edição</span><strong style="font-size:12px">{{ $stats['last_update'] ? \Carbon\Carbon::parse($stats['last_update'])->format('d/m/Y H:i') : '-' }}</strong><small>games_keys.updated_at</small></div>
            </div>
        </section>

        <section class="ga-card ga-form">
            {{ $this->form }}

            <div style="padding:16px 12px 10px;display:flex;justify-content:flex-end;border-top:1px solid rgba(163, 163, 163,.14);margin-top:12px">
                {{ $this->saveAggregatorAction }}
            </div>
        </section>

        <x-filament-actions::modals />
    </div>
</x-filament::page>
