<x-filament::page>
    <style>
        .theme-wrap{display:grid;gap:18px}
        .theme-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .theme-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .theme-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .theme-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .theme-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .theme-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.theme-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(min-width:1300px){.theme-stats{grid-template-columns:repeat(6,minmax(0,1fr))}}
        .theme-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .theme-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .theme-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .theme-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .theme-grid{display:grid;gap:12px;padding:0 20px 18px}
        @media(min-width:900px){.theme-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .theme-template{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.25);padding:12px}
        .theme-template strong{display:block;color:#fff;font-size:13px}
        .theme-template small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .swatches{display:flex;gap:5px;margin-top:10px}
        .swatches i{display:block;width:22px;height:22px;border-radius:7px;border:1px solid rgba(255,255,255,.14)}
        .theme-table{padding:8px}
        .theme-table .fi-ta-header{padding:10px 12px}
        .theme-table .fi-ta-filters{padding:0 10px 10px!important}
        .theme-table .fi-input,.theme-table .fi-select-input,.theme-table input,.theme-table select{min-height:38px!important}
        .theme-table .fi-input-wrp,.theme-table .fi-select{border-radius:12px!important}
    </style>

    @php($stats = $this->stats())
    @php($templates = $this->templates())

    <div class="theme-wrap">
        <section class="theme-card">
            <div class="theme-hero">
                <h2 class="theme-title">Cores do Tema</h2>
                <p class="theme-sub">
                    Página própria para aplicar templates prontos em todos os tokens do tema ou editar manualmente as cores do sistema. Os tokens ativos viram variáveis CSS usadas pelo tema.
                </p>

                <div class="theme-note">
                    Use <strong>Aplicar template</strong> para recalcular as 185 cores usando 25 templates, incluindo variações alpha, overlays, painéis e tokens misc. Depois, ajuste cores específicas pela tabela.
                </div>
            </div>

            <div class="theme-stats">
                <div class="theme-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Tokens cadastrados</small></div>
                <div class="theme-stat"><span>Ativas</span><strong>{{ $stats['active'] }}</strong><small>Aplicadas no CSS</small></div>
                <div class="theme-stat"><span>Editáveis</span><strong>{{ $stats['editable'] }}</strong><small>Permitidas para edição</small></div>
                <div class="theme-stat"><span>Essenciais</span><strong>{{ $stats['essentials'] }}</strong><small>Base do tema</small></div>
                <div class="theme-stat"><span>Famílias</span><strong>{{ $stats['families'] }}</strong><small>Grupos visuais</small></div>
                <div class="theme-stat"><span>Uso</span><strong>{{ $stats['occurrences'] }}</strong><small>Ocorrências mapeadas</small></div>
            </div>

            <div class="theme-grid">
                @foreach($templates as $template)
                    <div class="theme-template">
                        <strong>{{ $template['name'] }}</strong>
                        <small>Modelo pronto para aplicar nos 185 tokens mapeados.</small>
                        <div class="swatches">
                            <i style="background:{{ $template['palette']['primary'] ?? '#000' }}"></i>
                            <i style="background:{{ $template['palette']['accent'] ?? '#000' }}"></i>
                            <i style="background:{{ $template['palette']['background'] ?? '#000' }}"></i>
                            <i style="background:{{ $template['palette']['surface'] ?? '#000' }}"></i>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="theme-card theme-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>