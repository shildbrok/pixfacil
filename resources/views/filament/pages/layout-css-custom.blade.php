<x-filament::page>
    <style>
        .lc-wrap{display:grid;gap:18px}
        .lc-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .lc-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.13),transparent 32%)}
        .lc-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .lc-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .lc-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .lc-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.lc-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.lc-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .lc-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .lc-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .lc-stat strong{display:block;margin-top:4px;color:#fff;font-size:16px;font-weight:950}
        .lc-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .lc-preview{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.lc-preview{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .lc-img{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.25);overflow:hidden}
        .lc-img-box{height:116px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(255,255,255,.04),rgba(255,255,255,.02));padding:10px}
        .lc-img-box img{max-width:100%;max-height:100%;object-fit:contain;border-radius:10px}
        .lc-img-body{padding:10px}
        .lc-img-body strong{display:block;color:#fff;font-size:12px}
        .lc-img-body small{display:block;margin-top:3px;color:#a3a3a3;font-size:11px;word-break:break-all}
        .lc-form{padding:8px}
        .lc-form form{display:grid;gap:14px}
        .lc-form .fi-fo-tabs{border-radius:18px!important;overflow:hidden}
    </style>

    @php($stats = $this->stats())
    @php($previews = $this->previews())

    <div class="lc-wrap">
        <section class="lc-card">
            <div class="lc-hero">
                <h2 class="lc-title">Configurações Secundárias</h2>
                <p class="lc-sub">
                    Gerencie links, imagens, pop-ups, destaques e textos complementares do frontend.
                </p>

                <div class="lc-note">
                    As configurações continuam salvas na tabela <strong>custom_layouts</strong>. Ao salvar, o cache da API e dos assets é limpo automaticamente.
                </div>
            </div>

            <div class="lc-stats">
                <div class="lc-stat"><span>JivoChat</span><strong>{{ ($stats['jivochat'] ?? false) ? 'Ativo' : 'Sem token' }}</strong><small>Atendimento</small></div>
                <div class="lc-stat"><span>Links</span><strong>{{ $stats['links'] ?? 0 }}</strong><small>Configurados</small></div>
                <div class="lc-stat"><span>Imagens</span><strong>{{ $stats['images'] ?? 0 }}/8</strong><small>Arquivos preenchidos</small></div>
                <div class="lc-stat"><span>Maiores ganhos</span><strong>{{ ($stats['maiores'] ?? false) ? 'Ativo' : 'Inativo' }}</strong><small>Bloco promocional</small></div>
                <div class="lc-stat"><span>Lives ganhos</span><strong>{{ ($stats['lives'] ?? false) ? 'Ativo' : 'Inativo' }}</strong><small>Tempo real</small></div>
                <div class="lc-stat"><span>Rodadas grátis</span><strong>{{ ($stats['rodadas'] ?? false) ? 'Ativo' : 'Inativo' }}</strong><small>Pop-up</small></div>
                <div class="lc-stat"><span>Maior 18</span><strong>{{ ($stats['maior18'] ?? false) ? 'Ativo' : 'Inativo' }}</strong><small>Compliance</small></div>
                <div class="lc-stat"><span>Atualizado</span><strong style="font-size:12px">{{ ($stats['updated_at'] ?? null) ? \Carbon\Carbon::parse($stats['updated_at'])->format('d/m/Y H:i') : '-' }}</strong><small>custom_layouts</small></div>
            </div>

            <div class="lc-preview">
                @foreach($previews as $preview)
                    <div class="lc-img">
                        <div class="lc-img-box">
                            @if($this->imageUrl($preview['value'] ?? null))
                                <img src="{{ $this->imageUrl($preview['value']) }}" alt="{{ $preview['label'] }}">
                            @else
                                <span style="color:#737373;font-size:12px">Sem imagem</span>
                            @endif
                        </div>
                        <div class="lc-img-body">
                            <strong>{{ $preview['label'] }}</strong>
                            <small>{{ $preview['value'] ?: '-' }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="lc-card lc-form">
            <form wire:submit="submit">
                {{ $this->form }}

                <div style="padding:0 10px 10px">
                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Salvar configurações secundárias
                    </x-filament::button>
                </div>
            </form>
        </section>
    </div>
</x-filament::page>