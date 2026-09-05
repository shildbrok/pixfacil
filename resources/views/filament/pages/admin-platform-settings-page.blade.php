<x-filament::page>
    <style>
        .ps-wrap{display:grid;gap:18px}
        .ps-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .ps-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .ps-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .ps-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .ps-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .ps-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.ps-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.ps-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .ps-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .ps-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .ps-stat strong{display:block;margin-top:4px;color:#fff;font-size:16px;font-weight:950}
        .ps-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .ps-logos{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.ps-logos{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .ps-logo{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.25);overflow:hidden}
        .ps-logo-img{height:130px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(255,255,255,.04),rgba(255,255,255,.02));padding:14px}
        .ps-logo-img img{max-width:100%;max-height:100%;object-fit:contain}
        .ps-logo-body{padding:10px}
        .ps-logo-body strong{display:block;color:#fff;font-size:12px}
        .ps-logo-body small{display:block;margin-top:3px;color:#a3a3a3;font-size:11px;word-break:break-all}
        .ps-form{padding:8px}
        .ps-form form{display:grid;gap:14px}
        .ps-form .fi-fo-tabs{border-radius:18px!important;overflow:hidden}
    </style>

    @php($stats = $this->stats())

    <div class="ps-wrap">
        <section class="ps-card">
            <div class="ps-hero">
                <h2 class="ps-title">Configurações Primárias</h2>
                <p class="ps-sub">
                    Página própria para gerenciar identidade, SEO, roll-over, limites de saque e central financeira da plataforma.
                </p>

                <div class="ps-note">
                    Esta tela substitui o antigo SettingResource e suas páginas internas. As alterações continuam salvando na tabela <strong>settings</strong>.
                </div>
            </div>

            <div class="ps-stats">
                <div class="ps-stat"><span>Plataforma</span><strong>{{ $stats['name'] }}</strong><small>Nome público</small></div>
                <div class="ps-stat"><span>Depósito</span><strong>{{ $stats['deposit_gateway'] }}</strong><small>Gateway atual</small></div>
                <div class="ps-stat"><span>Saque</span><strong>{{ $stats['withdraw_gateway'] }}</strong><small>Gateway atual</small></div>
                <div class="ps-stat"><span>Depósito mín.</span><strong>{{ $stats['min_deposit'] }}</strong><small>Valor mínimo</small></div>
                <div class="ps-stat"><span>Saque mín.</span><strong>{{ $stats['min_withdrawal'] }}</strong><small>Valor mínimo</small></div>
                <div class="ps-stat"><span>Rollover</span><strong>{{ $stats['rollover_disabled'] ? 'Off' : 'On' }}</strong><small>Bônus {{ $stats['rollover'] }}x • Depósito {{ $stats['rollover_deposit'] }}x</small></div>
                <div class="ps-stat"><span>Auto saque</span><strong>{{ $stats['auto_approve'] ? 'Ativo' : 'Inativo' }}</strong><small>{{ $stats['auto_approve_max'] }}</small></div>
                <div class="ps-stat"><span>Indexação</span><strong>{{ $stats['indexing'] ? 'Permitida' : 'Bloqueada' }}</strong><small>{{ $stats['updated_at'] ? \Carbon\Carbon::parse($stats['updated_at'])->format('d/m/Y H:i') : '-' }}</small></div>
            </div>

            <div class="ps-logos">
                <div class="ps-logo">
                    <div class="ps-logo-img">
                        @if($stats['favicon'])
                            <img src="{{ $stats['favicon'] }}" alt="Favicon">
                        @else
                            <span style="color:#737373;font-size:12px">Sem favicon</span>
                        @endif
                    </div>
                    <div class="ps-logo-body">
                        <strong>Favicon</strong>
                        <small>{{ $stats['favicon'] ?: '-' }}</small>
                    </div>
                </div>

                <div class="ps-logo">
                    <div class="ps-logo-img">
                        @if($stats['logo_white'])
                            <img src="{{ $stats['logo_white'] }}" alt="Logo branco">
                        @else
                            <span style="color:#737373;font-size:12px">Sem logo branco</span>
                        @endif
                    </div>
                    <div class="ps-logo-body">
                        <strong>Logotipo de canto branco</strong>
                        <small>{{ $stats['logo_white'] ?: '-' }}</small>
                    </div>
                </div>

                <div class="ps-logo">
                    <div class="ps-logo-img">
                        @if($stats['logo_black'])
                            <img src="{{ $stats['logo_black'] }}" alt="Logo carregamento">
                        @else
                            <span style="color:#737373;font-size:12px">Sem logo de carregamento</span>
                        @endif
                    </div>
                    <div class="ps-logo-body">
                        <strong>Logo de carregamento</strong>
                        <small>{{ $stats['logo_black'] ?: '-' }}</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="ps-card ps-form">
            <form wire:submit="save">
                {{ $this->form }}

                <div style="padding:0 10px 10px">
                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Salvar configurações
                    </x-filament::button>
                </div>
            </form>
        </section>
    </div>
</x-filament::page>