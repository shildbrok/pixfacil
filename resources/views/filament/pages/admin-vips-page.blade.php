<x-filament::page>
    <style>
        .vip-wrap{display:grid;gap:18px}
        .vip-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .vip-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(245,158,11,.16),transparent 32%)}
        .vip-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .vip-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .vip-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .vip-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.vip-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.vip-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .vip-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .vip-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .vip-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .vip-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .vip-table{padding:10px}
        .vip-modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(10, 10, 10,.80);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .vip-modal{width:min(920px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(163, 163, 163,.20);border-radius:24px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45)}
        .vip-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .vip-modal-head h3{margin:0;color:#fff;font-size:18px;font-weight:950}
        .vip-modal-head p{margin:4px 0 0;color:#a3a3a3;font-size:12px}
        .vip-close{border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:rgba(20, 20, 20,.85);color:#fff;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer}
        .vip-modal-body{padding:18px;display:grid;gap:14px}
        .vip-preview{border:1px solid rgba(163, 163, 163,.16);border-radius:20px;background:radial-gradient(circle at top left,rgba(245,158,11,.14),#0a0a0a);padding:18px;display:grid;gap:16px}
        @media(min-width:840px){.vip-preview{grid-template-columns:240px 1fr;align-items:center}}
        .vip-avatar{display:flex;align-items:center;justify-content:center}
        .vip-avatar-inner{width:190px;height:190px;border-radius:999px;border:2px solid #f59e0b;background:#0a0a0a;padding:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 18px 45px rgba(0,0,0,.35)}
        .vip-avatar-inner img{width:100%;height:100%;object-fit:contain;border-radius:999px}
        .vip-preview-info{display:grid;gap:10px}
        .vip-preview-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .vip-preview-title{color:#fbbf24;font-size:22px;font-weight:950}
        .vip-pill{display:inline-flex;border-radius:999px;border:1px solid rgba(34,197,94,.55);color:#22c55e;background:rgba(34,197,94,.12);padding:3px 10px;font-size:11px;font-weight:900}
        .vip-preview-desc{color:#d4d4d4;font-size:13px;line-height:1.55}
        .vip-progress{height:10px;border-radius:999px;background:#141414;border:1px solid rgba(163, 163, 163,.20);overflow:hidden}
        .vip-progress div{width:65%;height:100%;background:linear-gradient(90deg,#f59e0b,#22c55e)}
        .vip-info-grid{display:grid;gap:10px}
        @media(min-width:800px){.vip-info-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .vip-info{border:1px solid rgba(163, 163, 163,.14);border-radius:14px;background:rgba(10, 10, 10,.30);padding:12px}
        .vip-info span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .vip-info strong{display:block;margin-top:4px;color:#fff;font-size:13px}
    </style>

    @php($stats = $this->stats())

    <div class="vip-wrap">
        <section class="vip-card">
            <div class="vip-hero">
                <h2 class="vip-title">Níveis VIP</h2>
                <p class="vip-sub">
                    Gerencie o Clube VIP, pontos necessários, recompensa semanal e imagem de cada nível exibido no frontend.
                </p>

                <div class="vip-note">
                    O frontend ordena os níveis por <strong>pontos necessários</strong> e calcula o nível atual com base nas missões resgatadas pelo usuário.
                </div>
            </div>

            <div class="vip-stats">
                <div class="vip-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Níveis cadastrados</small></div>
                <div class="vip-stat"><span>Bônus total</span><strong style="font-size:14px">{{ $this->money($stats['reward_sum']) }}</strong><small>Soma semanal</small></div>
                <div class="vip-stat"><span>Menor meta</span><strong>{{ $stats['lowest_points'] }}</strong><small>Pontos</small></div>
                <div class="vip-stat"><span>Maior meta</span><strong>{{ $stats['highest_points'] }}</strong><small>Pontos</small></div>
                <div class="vip-stat"><span>Resgates hoje</span><strong>{{ $stats['claims_today'] }}</strong><small>VIP semanal</small></div>
                <div class="vip-stat"><span>Resgates totais</span><strong>{{ $stats['claims_total'] }}</strong><small>Histórico</small></div>
                <div class="vip-stat"><span>Missões resgatadas</span><strong>{{ $stats['completed_missions'] }}</strong><small>Base de pontos</small></div>
                <div class="vip-stat"><span>Última edição</span><strong style="font-size:12px">{{ $stats['last_update'] ? \Carbon\Carbon::parse($stats['last_update'])->format('d/m/Y H:i') : '-' }}</strong><small>vips.updated_at</small></div>
            </div>
        </section>

        <section class="vip-card vip-table">
            {{ $this->table }}
        </section>
    </div>

    @if($showPreviewModal && $previewVip)
        @php($nextVip = $this->nextVip($previewVip))
        <div class="vip-modal-backdrop" wire:click.self="closePreview">
            <div class="vip-modal">
                <div class="vip-modal-head">
                    <div>
                        <h3>{{ $previewVip->title }}</h3>
                        <p>Prévia aproximada do card na página Vue</p>
                    </div>
                    <button type="button" class="vip-close" wire:click="closePreview">Fechar</button>
                </div>

                <div class="vip-modal-body">
                    <div class="vip-preview">
                        <div class="vip-avatar">
                            <div class="vip-avatar-inner">
                                @if($this->imageUrl($previewVip->image))
                                    <img src="{{ $this->imageUrl($previewVip->image) }}" alt="{{ $previewVip->title }}">
                                @else
                                    <span style="color:#737373;font-size:12px">Sem imagem</span>
                                @endif
                            </div>
                        </div>

                        <div class="vip-preview-info">
                            <div class="vip-preview-row">
                                <div class="vip-preview-title">{{ $previewVip->title }}</div>
                                <span class="vip-pill">{{ $previewVip->required_missions }} pts necessários</span>
                            </div>

                            <div class="vip-preview-desc">
                                {{ $previewVip->description ?: 'Este é um nível do Clube VIP.' }}
                            </div>

                            @if($nextVip)
                                <div class="vip-preview-desc">
                                    Próximo nível: <strong style="color:#22c55e">{{ $nextVip->title }}</strong>
                                </div>
                            @endif

                            <div class="vip-progress"><div></div></div>

                            <div class="vip-preview-desc">
                                Bônus semanal até <strong style="color:#fbbf24">{{ $this->money($previewVip->weekly_reward) }}</strong>.
                            </div>
                        </div>
                    </div>

                    <div class="vip-info-grid">
                        <div class="vip-info"><span>Pontos necessários</span><strong>{{ $previewVip->required_missions }}</strong></div>
                        <div class="vip-info"><span>Bônus semanal</span><strong>{{ $this->money($previewVip->weekly_reward) }}</strong></div>
                        <div class="vip-info"><span>Resgates neste nível</span><strong>{{ $previewVip->users()->whereNotNull('last_reward_claimed_at')->count() }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament::page>
