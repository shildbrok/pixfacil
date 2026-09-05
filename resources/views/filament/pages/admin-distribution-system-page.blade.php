<x-filament::page>
    <style>
        .dist-wrap{display:grid;gap:18px}
        .dist-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .dist-hero{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .dist-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .dist-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:920px}
        .dist-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
        .dist-btn{border:1px solid rgba(163, 163, 163,.2);border-radius:12px;background:rgba(20, 20, 20,.52);padding:9px 11px;color:#e5e7eb;font-size:12px;font-weight:900}
        .dist-btn.success{border-color:rgba(52,211,153,.25);background:rgba(16,185,129,.14);color:#bbf7d0}
        .dist-btn.warn{border-color:rgba(251,191,36,.25);background:rgba(245,158,11,.14);color:#fde68a}
        .dist-btn.danger{border-color:rgba(248,113,113,.25);background:rgba(239,68,68,.14);color:#fecaca}
        .dist-badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:13px}
        .dist-badge{border:1px solid rgba(163, 163, 163,.2);border-radius:999px;background:rgba(20, 20, 20,.52);padding:7px 10px;color:#e5e7eb;font-size:12px;font-weight:800}
        .dist-badge.success{border-color:rgba(52,211,153,.3);color:#bbf7d0}
        .dist-badge.warn{border-color:rgba(251,191,36,.3);color:#fde68a}
        .dist-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.dist-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.dist-stats{grid-template-columns:repeat(6,minmax(0,1fr))}}
        .dist-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .dist-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .dist-stat strong{display:block;margin-top:4px;color:#fff;font-size:17px;font-weight:950}
        .dist-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .dist-grid{display:grid;gap:16px}
        @media(min-width:1100px){.dist-grid.two{grid-template-columns:1.1fr .9fr}}
        .dist-section{padding:18px 20px}
        .dist-section h3{margin:0 0 12px;color:#fff;font-size:16px;font-weight:900}
        .dist-progress{height:13px;border-radius:999px;background:rgba(163, 163, 163,.18);overflow:hidden}
        .dist-progress-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#fb923c,#22c55e)}
        .dist-note{border:1px solid rgba(251,191,36,.2);border-radius:16px;background:rgba(120,53,15,.20);padding:13px;color:#fde68a;font-size:12px;line-height:1.55}
        .dist-form{padding:8px}
        .dist-form form{display:grid;gap:14px}
    </style>

    @php($stats = $this->stats())

    <div class="dist-wrap">
        <section class="dist-card">
            <div class="dist-hero">
                <div>
                    <h2 class="dist-title">Distribuição de Ganhos</h2>
                    <p class="dist-sub">
                        Página própria para controlar meta de arrecadação, percentual de distribuição, RTP por modo e status operacional.
                    </p>

                    <div class="dist-badges">
                        <span class="dist-badge {{ $stats['ativo'] ? 'success' : 'warn' }}">
                            Sistema: {{ $stats['ativo'] ? 'Ativo' : 'Inativo' }}
                        </span>
                        <span class="dist-badge {{ $stats['modo'] === 'distribuicao' ? 'success' : 'warn' }}">
                            Modo: {{ $stats['modo'] === 'distribuicao' ? 'Distribuição' : 'Arrecadação' }}
                        </span>
                        <span class="dist-badge">
                            Ciclo: {{ $stats['start_cycle_at'] ? \Carbon\Carbon::parse($stats['start_cycle_at'])->format('d/m/Y H:i') : '-' }}
                        </span>
                    </div>
                </div>

                <div class="dist-actions">
                    <button type="button" wire:click="toggleActive" class="dist-btn {{ $stats['ativo'] ? 'danger' : 'success' }}">
                        {{ $stats['ativo'] ? 'Desativar' : 'Ativar' }}
                    </button>
                    <button type="button" wire:click="setMode('arrecadacao')" class="dist-btn warn">Arrecadação</button>
                    <button type="button" wire:click="setMode('distribuicao')" class="dist-btn success">Distribuição</button>
                    <button type="button" wire:click="resetCycle" wire:confirm="Reiniciar ciclo e zerar totais?" class="dist-btn danger">Reiniciar ciclo</button>
                </div>
            </div>

            <div class="dist-stats">
                <div class="dist-stat"><span>Total arrecadado</span><strong>{{ $this->money($stats['total_arrecadado']) }}</strong><small>Acumulado do ciclo</small></div>
                <div class="dist-stat"><span>Total distribuído</span><strong>{{ $this->money($stats['total_distribuido']) }}</strong><small>Ganhos distribuídos</small></div>
                <div class="dist-stat"><span>Meta</span><strong>{{ $this->money($stats['meta_arrecadacao']) }}</strong><small>{{ $this->percent($stats['progresso']) }} concluído</small></div>
                <div class="dist-stat"><span>Distribuição prevista</span><strong>{{ $this->money($stats['valor_previsto_distribuicao']) }}</strong><small>{{ $this->percent($stats['percentual_distribuicao']) }} do arrecadado</small></div>
                <div class="dist-stat"><span>Saldo para distribuir</span><strong>{{ $this->money($stats['saldo_distribuicao']) }}</strong><small>Previsto - distribuído</small></div>
                <div class="dist-stat"><span>Lucro hoje</span><strong>{{ $this->money($stats['house_today']) }}</strong><small>Apostas - ganhos</small></div>
            </div>
        </section>

        <div class="dist-grid two">
            <section class="dist-card dist-section">
                <h3>Progresso da meta</h3>
                <div class="dist-progress">
                    <div class="dist-progress-fill" style="width: {{ min(100, max(0, $stats['progresso'])) }}%"></div>
                </div>
                <p style="margin:10px 0 0;color:#d4d4d4;font-size:13px">
                    {{ $this->money($stats['total_arrecadado']) }} arrecadado de {{ $this->money($stats['meta_arrecadacao']) }}.
                </p>

                <div class="dist-stats" style="padding:14px 0 0;grid-template-columns:repeat(2,minmax(0,1fr))">
                    <div class="dist-stat"><span>RTP arrecadação</span><strong>{{ $this->percent($stats['rtp_arrecadacao']) }}</strong></div>
                    <div class="dist-stat"><span>RTP distribuição</span><strong>{{ $this->percent($stats['rtp_distribuicao']) }}</strong></div>
                    <div class="dist-stat"><span>Apostas hoje</span><strong>{{ $this->money($stats['bets_today']) }}</strong></div>
                    <div class="dist-stat"><span>Ganhos hoje</span><strong>{{ $this->money($stats['wins_today']) }}</strong></div>
                </div>

                <div class="dist-note" style="margin-top:14px">
                    Use esta tela com cuidado. Alterar RTP, meta e modo pode impactar diretamente o comportamento financeiro da casa.
                </div>
            </section>

            <section class="dist-card dist-section dist-form">
                <h3>Configuração</h3>

                <form wire:submit="save">
                    {{ $this->form }}

                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Salvar configurações
                    </x-filament::button>
                </form>
            </section>
        </div>
    </div>
</x-filament::page>
