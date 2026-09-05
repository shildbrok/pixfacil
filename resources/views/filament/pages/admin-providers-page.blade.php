<x-filament::page>
    <style>
        .pv-wrap{display:grid;gap:18px}
        .pv-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .pv-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.13),transparent 32%)}
        .pv-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .pv-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .pv-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .pv-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.pv-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.pv-stats{grid-template-columns:repeat(9,minmax(0,1fr))}}
        .pv-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .pv-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .pv-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .pv-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .pv-table{padding:10px}
        .pv-modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(10, 10, 10,.80);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .pv-modal{width:min(900px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(163, 163, 163,.20);border-radius:24px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45)}
        .pv-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .pv-modal-head h3{margin:0;color:#fff;font-size:18px;font-weight:950}
        .pv-modal-head p{margin:4px 0 0;color:#a3a3a3;font-size:12px}
        .pv-close{border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:rgba(20, 20, 20,.85);color:#fff;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer}
        .pv-modal-body{padding:18px;display:grid;gap:14px}
        .pv-preview{border:1px solid rgba(163, 163, 163,.16);border-radius:20px;background:#0a0a0a;overflow:hidden}
        .pv-preview-logo{height:260px;background:#0a0a0a;display:flex;align-items:center;justify-content:center;padding:24px}
        .pv-preview-logo img{width:100%;height:100%;object-fit:contain}
        .pv-preview-body{padding:16px;display:grid;gap:8px}
        .pv-preview-title{color:#fff;font-size:20px;font-weight:950}
        .pv-preview-desc{color:#d4d4d4;font-size:13px;line-height:1.55}
        .pv-pills{display:flex;gap:8px;flex-wrap:wrap}
        .pv-pill{display:inline-flex;width:max-content;border-radius:999px;background:rgba(249, 115, 22,.14);color:#fdba74;border:1px solid rgba(249, 115, 22,.24);padding:4px 10px;font-size:11px;font-weight:900}
        .pv-pill-ok{background:rgba(34,197,94,.14);color:#22c55e;border-color:rgba(34,197,94,.24)}
        .pv-pill-bad{background:rgba(239,68,68,.14);color:#ef4444;border-color:rgba(239,68,68,.24)}
        .pv-info-grid{display:grid;gap:10px}
        @media(min-width:800px){.pv-info-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .pv-info{border:1px solid rgba(163, 163, 163,.14);border-radius:14px;background:rgba(10, 10, 10,.30);padding:12px}
        .pv-info span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .pv-info strong{display:block;margin-top:4px;color:#fff;font-size:13px;word-break:break-all}
    </style>

    @php($stats = $this->stats())

    <div class="pv-wrap">
        <section class="pv-card">
            <div class="pv-hero">
                <h2 class="pv-title">Provedores de Jogos</h2>
                <p class="pv-sub">
                    Gerencie provedores do catálogo, distribuição PlayFiver, logos, status e métricas dos jogos vinculados.
                </p>

                <div class="pv-note">
                    Os totais de jogos são calculados diretamente pela tabela <strong>games.provider_id</strong>, sem usar a relação filtrada por home do model.
                </div>
            </div>

            <div class="pv-stats">
                <div class="pv-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Provedores</small></div>
                <div class="pv-stat"><span>Ativos</span><strong>{{ $stats['active'] }}</strong><small>Status 1</small></div>
                <div class="pv-stat"><span>Inativos</span><strong>{{ $stats['inactive'] }}</strong><small>Status 0</small></div>
                <div class="pv-stat"><span>Sem logo</span><strong>{{ $stats['without_cover'] }}</strong><small>Revisar visual</small></div>
                <div class="pv-stat"><span>PlayFiver</span><strong>{{ $stats['play_fiver'] }}</strong><small>distribution</small></div>
                <div class="pv-stat"><span>Jogos</span><strong>{{ $stats['games_total'] }}</strong><small>Total catálogo</small></div>
                <div class="pv-stat"><span>Jogos ativos</span><strong>{{ $stats['games_active'] }}</strong><small>Disponíveis</small></div>
                <div class="pv-stat"><span>Home</span><strong>{{ $stats['games_home'] }}</strong><small>show_home</small></div>
                <div class="pv-stat"><span>Última edição</span><strong style="font-size:12px">{{ $stats['last_update'] ? \Carbon\Carbon::parse($stats['last_update'])->format('d/m/Y H:i') : '-' }}</strong><small>providers.updated_at</small></div>
            </div>
        </section>

        <section class="pv-card pv-table">
            {{ $this->table }}
        </section>
    </div>

    @if($showPreviewModal && $previewProvider)
        <div class="pv-modal-backdrop" wire:click.self="closePreview">
            <div class="pv-modal">
                <div class="pv-modal-head">
                    <div>
                        <h3>{{ $previewProvider->name }}</h3>
                        <p>{{ strtoupper($previewProvider->code) }} • {{ $previewProvider->distribution ?: 'sem distribuição' }}</p>
                    </div>
                    <button type="button" class="pv-close" wire:click="closePreview">Fechar</button>
                </div>

                <div class="pv-modal-body">
                    <div class="pv-preview">
                        <div class="pv-preview-logo">
                            @if($this->imageUrl($previewProvider->cover))
                                <img src="{{ $this->imageUrl($previewProvider->cover) }}" alt="{{ $previewProvider->name }}">
                            @else
                                <span style="color:#737373;font-size:12px">Sem logo</span>
                            @endif
                        </div>

                        <div class="pv-preview-body">
                            <div class="pv-preview-title">{{ $previewProvider->name }}</div>

                            <div class="pv-pills">
                                <span class="pv-pill {{ $previewProvider->status ? 'pv-pill-ok' : 'pv-pill-bad' }}">{{ $previewProvider->status ? 'Ativo' : 'Inativo' }}</span>
                                <span class="pv-pill">{{ strtoupper($previewProvider->code) }}</span>
                                @if($previewProvider->distribution)<span class="pv-pill">{{ $previewProvider->distribution }}</span>@endif
                            </div>

                            <div class="pv-preview-desc">
                                Provedor com {{ $this->gamesTotal($previewProvider) }} jogos cadastrados no catálogo local.
                            </div>
                        </div>
                    </div>

                    <div class="pv-info-grid">
                        <div class="pv-info"><span>Jogos</span><strong>{{ $this->gamesTotal($previewProvider) }}</strong></div>
                        <div class="pv-info"><span>Ativos</span><strong>{{ $this->gamesActive($previewProvider) }}</strong></div>
                        <div class="pv-info"><span>Home</span><strong>{{ $this->gamesHome($previewProvider) }}</strong></div>
                        <div class="pv-info"><span>Destaques</span><strong>{{ $this->gamesFeatured($previewProvider) }}</strong></div>
                        <div class="pv-info"><span>Views</span><strong>{{ number_format((int) $previewProvider->views, 0, ',', '.') }}</strong></div>
                        <div class="pv-info"><span>Criado</span><strong>{{ $previewProvider->created_at?->format('d/m/Y H:i') ?: '—' }}</strong></div>
                        <div class="pv-info"><span>Atualizado</span><strong>{{ $previewProvider->updated_at?->format('d/m/Y H:i') ?: '—' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament::page>