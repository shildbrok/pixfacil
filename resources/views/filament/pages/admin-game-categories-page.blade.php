<x-filament::page>
    <style>
        .cat-wrap{display:grid;gap:18px}
        .cat-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .cat-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .cat-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .cat-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .cat-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .cat-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.cat-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(min-width:1300px){.cat-stats{grid-template-columns:repeat(7,minmax(0,1fr))}}
        .cat-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .cat-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .cat-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .cat-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .cat-table{padding:10px}
        .cat-modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(10, 10, 10,.80);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .cat-modal{width:min(900px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(163, 163, 163,.20);border-radius:24px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45)}
        .cat-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .cat-modal-head h3{margin:0;color:#fff;font-size:18px;font-weight:950}
        .cat-modal-head p{margin:4px 0 0;color:#a3a3a3;font-size:12px}
        .cat-close{border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:rgba(20, 20, 20,.85);color:#fff;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer}
        .cat-modal-body{padding:18px;display:grid;gap:14px}
        .cat-preview{border:1px solid rgba(163, 163, 163,.16);border-radius:20px;background:#0a0a0a;overflow:hidden}
        .cat-preview-img{height:260px;background:#0a0a0a;display:flex;align-items:center;justify-content:center}
        .cat-preview-img img{width:100%;height:100%;object-fit:cover}
        .cat-preview-body{padding:16px;display:grid;gap:8px}
        .cat-preview-title{color:#fff;font-size:20px;font-weight:950}
        .cat-preview-desc{color:#d4d4d4;font-size:13px;line-height:1.55}
        .cat-pill{display:inline-flex;width:max-content;border-radius:999px;background:rgba(249, 115, 22,.14);color:#fdba74;border:1px solid rgba(249, 115, 22,.24);padding:4px 10px;font-size:11px;font-weight:900}
        .cat-info-grid{display:grid;gap:10px}
        @media(min-width:800px){.cat-info-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .cat-info{border:1px solid rgba(163, 163, 163,.14);border-radius:14px;background:rgba(10, 10, 10,.30);padding:12px}
        .cat-info span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .cat-info strong{display:block;margin-top:4px;color:#fff;font-size:13px;word-break:break-all}
    </style>

    @php($stats = $this->stats())

    <div class="cat-wrap">
        <section class="cat-card">
            <div class="cat-hero">
                <h2 class="cat-title">Categorias de Jogos</h2>
                <p class="cat-sub">
                    Organize o catálogo por categorias exibidas no frontend e usadas pelas APIs de jogos.
                </p>

                <div class="cat-note">
                    A tabela categories guarda as categorias. A tabela category_game liga cada categoria aos jogos. Ao salvar, esta página limpa o cache e renova a versão de assets.
                </div>
            </div>

            <div class="cat-stats">
                <div class="cat-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Categorias</small></div>
                <div class="cat-stat"><span>Sem imagem</span><strong>{{ $stats['without_image'] }}</strong><small>Revisar visual</small></div>
                <div class="cat-stat"><span>Sem slug</span><strong>{{ $stats['without_slug'] }}</strong><small>Nome na home</small></div>
                <div class="cat-stat"><span>Com link</span><strong>{{ $stats['with_link'] }}</strong><small>Redirecionamento</small></div>
                <div class="cat-stat"><span>Vínculos</span><strong>{{ $stats['linked_games'] }}</strong><small>{{ $stats['pivot_table'] }}</small></div>
                <div class="cat-stat"><span>Jogos únicos</span><strong>{{ $stats['unique_linked_games'] }}</strong><small>distintos em category_game</small></div>
                <div class="cat-stat"><span>Última edição</span><strong style="font-size:12px">{{ $stats['last_update'] ? \Carbon\Carbon::parse($stats['last_update'])->format('d/m/Y H:i') : '-' }}</strong><small>categories.updated_at</small></div>
            </div>
        </section>

        <section class="cat-card cat-table">
            {{ $this->table }}
        </section>
    </div>

    @if($showPreviewModal && $previewCategory)
        <div class="cat-modal-backdrop" wire:click.self="closePreview">
            <div class="cat-modal">
                <div class="cat-modal-head">
                    <div>
                        <h3>{{ $previewCategory->name }}</h3>
                        <p>Prévia visual da categoria no frontend</p>
                    </div>
                    <button type="button" class="cat-close" wire:click="closePreview">Fechar</button>
                </div>

                <div class="cat-modal-body">
                    <div class="cat-preview">
                        <div class="cat-preview-img">
                            @if($this->imageUrl($previewCategory->image))
                                <img src="{{ $this->imageUrl($previewCategory->image) }}" alt="{{ $previewCategory->name }}">
                            @else
                                <span style="color:#737373;font-size:12px">Sem imagem</span>
                            @endif
                        </div>

                        <div class="cat-preview-body">
                            <div class="cat-preview-title">{{ $previewCategory->name }}</div>
                            <div class="cat-preview-desc">{{ $previewCategory->description ?: 'Sem descrição.' }}</div>
                            <span class="cat-pill">{{ $previewCategory->slug ?: 'sem-slug' }}</span>
                        </div>
                    </div>

                    <div class="cat-info-grid">
                        <div class="cat-info"><span>Jogos vinculados</span><strong>{{ $this->gamesCount($previewCategory) }}</strong></div>
                        <div class="cat-info"><span>Link</span><strong>{{ $previewCategory->url ?: '—' }}</strong></div>
                        <div class="cat-info"><span>Atualizada</span><strong>{{ $previewCategory->updated_at?->format('d/m/Y H:i') ?: '—' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament::page>