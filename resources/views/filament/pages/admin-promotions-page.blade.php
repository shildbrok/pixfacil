<x-filament::page>
    <style>
        .pr-wrap{display:grid;gap:18px}
        .pr-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .pr-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.13),transparent 32%)}
        .pr-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .pr-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .pr-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .pr-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.pr-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(min-width:1300px){.pr-stats{grid-template-columns:repeat(6,minmax(0,1fr))}}
        .pr-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .pr-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .pr-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .pr-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .pr-table{padding:10px}
        .pr-modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(10, 10, 10,.80);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .pr-modal{width:min(920px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(163, 163, 163,.20);border-radius:24px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45)}
        .pr-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .pr-modal-head h3{margin:0;color:#fff;font-size:18px;font-weight:950}
        .pr-modal-head p{margin:4px 0 0;color:#a3a3a3;font-size:12px}
        .pr-close{border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:rgba(20, 20, 20,.85);color:#fff;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer}
        .pr-modal-body{padding:18px;display:grid;gap:14px}
        .pr-preview-img{width:100%;max-height:360px;object-fit:cover;border-radius:18px;border:1px solid rgba(163, 163, 163,.16);background:#0a0a0a}
        .pr-preview-box{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.30);padding:14px;color:#d4d4d4;font-size:13px;line-height:1.6}
        .pr-preview-box h1,.pr-preview-box h2,.pr-preview-box h3{color:#fff}
        .pr-link{color:#fdba74;text-decoration:none;font-weight:800;font-size:13px}
    </style>

    @php($stats = $this->stats())

    <div class="pr-wrap">
        <section class="pr-card">
            <div class="pr-hero">
                <h2 class="pr-title">Campanhas Promocionais</h2>
                <p class="pr-sub">
                    Gerencie as promoções exibidas no frontend, com imagem, link de participação e regras completas.
                </p>

                <div class="pr-note">
                    As campanhas são lidas pela rota <strong>promocoes</strong>. A página Vue usa <strong>titulo</strong>, <strong>imagem</strong>, <strong>link</strong> e <strong>regras_html</strong>; o tipo e a descrição curta são calculados pelo título.
                </div>
            </div>

            <div class="pr-stats">
                <div class="pr-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Campanhas</small></div>
                <div class="pr-stat"><span>Com regras</span><strong>{{ $stats['with_rules'] }}</strong><small>Popup configurado</small></div>
                <div class="pr-stat"><span>Com link</span><strong>{{ $stats['with_link'] }}</strong><small>Botão participar</small></div>
                <div class="pr-stat"><span>Sem imagem</span><strong>{{ $stats['without_image'] }}</strong><small>Revisar cadastro</small></div>
                <div class="pr-stat"><span>Atualizadas hoje</span><strong>{{ $stats['updated_today'] }}</strong><small>Manutenção recente</small></div>
                <div class="pr-stat"><span>Última edição</span><strong style="font-size:12px">{{ $stats['last_update'] ? \Carbon\Carbon::parse($stats['last_update'])->format('d/m/Y H:i') : '-' }}</strong><small>promocoes.updated_at</small></div>
            </div>
        </section>

        <section class="pr-card pr-table">
            {{ $this->table }}
        </section>
    </div>

    @if($showPreviewModal && $previewPromotion)
        <div class="pr-modal-backdrop" wire:click.self="closePreview">
            <div class="pr-modal">
                <div class="pr-modal-head">
                    <div>
                        <h3>{{ $previewPromotion->titulo }}</h3>
                        <p>{{ $this->detectType($previewPromotion) }} • Igual ao card Vue</p>
                    </div>
                    <button type="button" class="pr-close" wire:click="closePreview">Fechar</button>
                </div>

                <div class="pr-modal-body">
                    @if($this->imageUrl($previewPromotion->imagem))
                        <img class="pr-preview-img" src="{{ $this->imageUrl($previewPromotion->imagem) }}" alt="{{ $previewPromotion->titulo }}">
                    @endif

                    <div class="pr-preview-box">
                        <strong style="color:#fff;display:block;margin-bottom:6px">Descrição curta que o Vue exibirá</strong>
                        {{ $this->shortDescriptionForType($this->detectType($previewPromotion)) }}
                    </div>

                    @if($previewPromotion->link)
                        <a class="pr-link" href="{{ $previewPromotion->link }}" target="_blank">Abrir link da campanha</a>
                    @else
                        <div class="pr-preview-box">Sem link de ativação. O botão “Participar da promoção” não aparecerá no frontend.</div>
                    @endif

                    <div class="pr-preview-box">
                        @if($previewPromotion->regras_html)
                            {!! $previewPromotion->regras_html !!}
                        @else
                            Sem regras cadastradas.
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament::page>