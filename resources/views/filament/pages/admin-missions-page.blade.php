<x-filament::page>
    <style>
        .msn-wrap{display:grid;gap:18px}
        .msn-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .msn-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .msn-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .msn-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .msn-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .msn-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.msn-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.msn-stats{grid-template-columns:repeat(7,minmax(0,1fr))}}
        .msn-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .msn-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .msn-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .msn-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .msn-table{padding:10px}
        .msn-modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(10, 10, 10,.80);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .msn-modal{width:min(820px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(163, 163, 163,.20);border-radius:24px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45)}
        .msn-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .msn-modal-head h3{margin:0;color:#fff;font-size:18px;font-weight:950}
        .msn-modal-head p{margin:4px 0 0;color:#a3a3a3;font-size:12px}
        .msn-close{border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:rgba(20, 20, 20,.85);color:#fff;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer}
        .msn-modal-body{padding:18px;display:grid;gap:14px}
        .msn-preview-card{border:1px solid rgba(163, 163, 163,.14);border-radius:18px;background:#0a0a0a;overflow:hidden;box-shadow:0 18px 40px rgba(0,0,0,.28)}
        .msn-preview-img{height:260px;display:flex;align-items:center;justify-content:center;background:#0a0a0a}
        .msn-preview-img img{width:100%;height:100%;object-fit:contain}
        .msn-preview-content{padding:14px;display:grid;gap:8px}
        .msn-preview-row{display:flex;align-items:center;justify-content:space-between;gap:10px}
        .msn-preview-title{color:#fff;font-size:16px;font-weight:950}
        .msn-pill{display:inline-flex;border-radius:999px;background:#22c55e;color:#0a0a0a;padding:4px 10px;font-size:11px;font-weight:950;white-space:nowrap}
        .msn-type{display:inline-flex;border-radius:999px;background:rgba(249, 115, 22,.14);color:#fdba74;border:1px solid rgba(249, 115, 22,.24);padding:3px 9px;font-size:11px;font-weight:900}
        .msn-desc{color:#d4d4d4;font-size:13px;line-height:1.45}
        .msn-progress{height:9px;border-radius:999px;background:#141414;border:1px solid rgba(163, 163, 163,.20);overflow:hidden}
        .msn-progress div{width:45%;height:100%;background:#22c55e}
        .msn-info{display:grid;gap:10px}
        @media(min-width:800px){.msn-info{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .msn-info-box{border:1px solid rgba(163, 163, 163,.14);border-radius:14px;background:rgba(10, 10, 10,.30);padding:12px}
        .msn-info-box span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .msn-info-box strong{display:block;margin-top:4px;color:#fff;font-size:13px}
    </style>

    @php($stats = $this->stats())

    <div class="msn-wrap">
        <section class="msn-card">
            <div class="msn-hero">
                <h2 class="msn-title">Missões e Desafios</h2>
                <p class="msn-sub">
                    Crie missões promocionais para incentivar depósitos, apostas, rodadas, ganhos e desafios em jogos específicos.
                </p>

                <div class="msn-note">
                    As missões ativas são lidas pela rota <strong>missions</strong>. O progresso é diário e a recompensa é creditada como bônus na carteira.
                </div>
            </div>

            <div class="msn-stats">
                <div class="msn-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Missões criadas</small></div>
                <div class="msn-stat"><span>Ativas</span><strong>{{ $stats['active'] }}</strong><small>Aparecem no frontend</small></div>
                <div class="msn-stat"><span>Inativas</span><strong>{{ $stats['inactive'] }}</strong><small>Ocultas do frontend</small></div>
                <div class="msn-stat"><span>Resgates hoje</span><strong>{{ $stats['redeemed_today'] }}</strong><small>Bônus pagos hoje</small></div>
                <div class="msn-stat"><span>Resgates totais</span><strong>{{ $stats['redeemed_total'] }}</strong><small>Histórico</small></div>
                <div class="msn-stat"><span>Recompensas</span><strong style="font-size:14px">{{ $this->money($stats['reward_total']) }}</strong><small>Soma cadastrada</small></div>
                <div class="msn-stat"><span>Última edição</span><strong style="font-size:12px">{{ $stats['last_update'] ? \Carbon\Carbon::parse($stats['last_update'])->format('d/m/Y H:i') : '-' }}</strong><small>missions.updated_at</small></div>
            </div>
        </section>

        <section class="msn-card msn-table">
            {{ $this->table }}
        </section>
    </div>

    @if($showPreviewModal && $previewMission)
        <div class="msn-modal-backdrop" wire:click.self="closePreview">
            <div class="msn-modal">
                <div class="msn-modal-head">
                    <div>
                        <h3>{{ $previewMission->title }}</h3>
                        <p>{{ $this->missionTypeOptions()[$previewMission->type] ?? $previewMission->type }}</p>
                    </div>
                    <button type="button" class="msn-close" wire:click="closePreview">Fechar</button>
                </div>

                <div class="msn-modal-body">
                    <div class="msn-preview-card">
                        <div class="msn-preview-img">
                            @if($this->imageUrl($previewMission->image))
                                <img src="{{ $this->imageUrl($previewMission->image) }}" alt="{{ $previewMission->title }}">
                            @else
                                <span style="color:#737373;font-size:12px">Sem imagem</span>
                            @endif
                        </div>

                        <div class="msn-preview-content">
                            <div class="msn-preview-row">
                                <div class="msn-preview-title">{{ $previewMission->title }}</div>
                                <div class="msn-pill">{{ $this->money($previewMission->reward) }} Bônus</div>
                            </div>

                            <div class="msn-preview-row" style="justify-content:flex-start">
                                <span class="msn-type">{{ $this->missionTypeLabel($previewMission->type) }}</span>
                                <span class="msn-desc">{{ $this->shortDescriptionForType($previewMission->type, $previewMission->target_amount) }}</span>
                            </div>

                            @if($previewMission->description)
                                <div class="msn-desc">{{ $previewMission->description }}</div>
                            @endif

                            <div class="msn-progress"><div></div></div>
                            <div class="msn-desc">Prévia visual do card. O progresso real é calculado por usuário no frontend.</div>
                        </div>
                    </div>

                    <div class="msn-info">
                        <div class="msn-info-box"><span>Meta</span><strong>{{ $this->formatTarget($previewMission) }}</strong></div>
                        <div class="msn-info-box"><span>Jogo vinculado</span><strong>{{ $this->gameName($previewMission->game_id) }}</strong></div>
                        <div class="msn-info-box"><span>Status</span><strong>{{ $previewMission->status === 'active' ? 'Ativa' : 'Inativa' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament::page>
