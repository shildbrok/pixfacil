<x-filament::page>
    <style>
        .gs-wrap{display:grid;gap:18px}
        .gs-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .gs-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.20),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.14),transparent 32%)}
        .gs-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .gs-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:1020px}
        .gs-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .gs-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.gs-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.gs-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .gs-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .gs-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .gs-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .gs-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .gs-panel{padding:14px;display:grid;gap:14px}
        .gs-toolbar{display:grid;gap:12px}
        @media(min-width:1000px){.gs-toolbar{grid-template-columns:1fr auto;align-items:center}}
        .gs-search-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .gs-search{width:min(420px,100%);border:1px solid rgba(163, 163, 163,.22);border-radius:13px;background:#0a0a0a;color:#fff;padding:11px 13px;font-size:13px}
        .gs-filter-tabs{display:flex;gap:8px;flex-wrap:wrap}
        .gs-filter{border:1px solid rgba(163, 163, 163,.18);border-radius:999px;background:rgba(20, 20, 20,.65);color:#d4d4d4;padding:8px 12px;font-size:12px;font-weight:900;cursor:pointer}
        .gs-filter-active{background:#ea580c;border-color:#fb923c;color:#fff}
        .gs-actions{display:flex;gap:8px;flex-wrap:wrap}
        .gs-btn{border:0;border-radius:12px;color:#fff;padding:9px 12px;font-size:12px;font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
        .gs-btn-primary{background:#ea580c}
        .gs-btn-success{background:#16a34a}
        .gs-btn-warning{background:#d97706}
        .gs-btn-danger{background:#dc2626}
        .gs-btn-gray{background:#404040}
        .gs-table-card{border:1px solid rgba(163, 163, 163,.14);border-radius:18px;background:rgba(10, 10, 10,.26);overflow:hidden}
        .gs-table-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:14px;border-bottom:1px solid rgba(163, 163, 163,.12);background:rgba(20, 20, 20,.65)}
        .gs-table-head h3{margin:0;color:#fff;font-size:15px;font-weight:950}
        .gs-count{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:900;background:rgba(249, 115, 22,.14);color:#fdba74;border:1px solid rgba(249, 115, 22,.24)}
        .gs-table-wrap{overflow-x:auto}
        .gs-table{width:100%;border-collapse:collapse;min-width:980px}
        .gs-table th{background:rgba(20, 20, 20,.55);color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;text-align:left;padding:11px;border-bottom:1px solid rgba(163, 163, 163,.12)}
        .gs-table td{color:#d4d4d4;font-size:12px;padding:11px;border-bottom:1px solid rgba(163, 163, 163,.09);vertical-align:middle}
        .gs-user strong{display:block;color:#fff;font-size:13px}
        .gs-user span{display:block;color:#a3a3a3;font-size:11px;margin-top:2px}
        .gs-badge{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:900;border:1px solid rgba(163, 163, 163,.16)}
        .gs-badge-ok{background:rgba(34,197,94,.12);color:#22c55e;border-color:rgba(34,197,94,.25)}
        .gs-badge-warn{background:rgba(245,158,11,.12);color:#fbbf24;border-color:rgba(245,158,11,.25)}
        .gs-badge-info{background:rgba(249, 115, 22,.12);color:#fdba74;border-color:rgba(249, 115, 22,.25)}
        .gs-badge-muted{background:rgba(163, 163, 163,.12);color:#d4d4d4;border-color:rgba(163, 163, 163,.25)}
        .gs-empty{padding:22px;color:#a3a3a3;font-size:13px;text-align:center}
        .gs-modal-backdrop{position:fixed;inset:0;z-index:80;background:rgba(10, 10, 10,.78);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .gs-modal{width:min(900px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(163, 163, 163,.22);border-radius:22px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45)}
        .gs-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .gs-modal-head h3{margin:0;color:#fff;font-size:17px;font-weight:950}
        .gs-modal-head p{margin:6px 0 0;color:#a3a3a3;font-size:12px;line-height:1.45}
        .gs-close{border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:rgba(20, 20, 20,.85);color:#fff;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer}
        .gs-modal-body{padding:18px 20px;display:grid;gap:12px}
        .gs-info-grid{display:grid;gap:10px}
        @media(min-width:800px){.gs-info-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .gs-info{border:1px solid rgba(163, 163, 163,.14);border-radius:14px;background:rgba(10, 10, 10,.30);padding:12px}
        .gs-info span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .gs-info strong{display:block;margin-top:4px;color:#fff;font-size:13px;word-break:break-all}
    </style>

    @php($stats = $this->stats())
    @php($sessions = $this->sessions())

    <div class="gs-wrap">
        <section class="gs-card">
            <div class="gs-hero">
                <h2 class="gs-title">Sessões de Jogos</h2>
                <p class="gs-sub">
                    Gerencie sessões abertas pelo GameSession.php em uma tabela única, com filtros por status e ações rápidas por linha.
                </p>

                <div class="gs-note">
                    Sessões ativas podem ser desconectadas. Sessões expiradas ou fechadas podem ser excluídas. Use os filtros para organizar a visualização.
                </div>
            </div>

            <div class="gs-stats">
                <div class="gs-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>game_sessions</small></div>
                <div class="gs-stat"><span>Ativas</span><strong>{{ $stats['active'] }}</strong><small>status active</small></div>
                <div class="gs-stat"><span>Ativas reais</span><strong>{{ $stats['active_last_5_min'] }}</strong><small>ping últimos 5 min</small></div>
                <div class="gs-stat"><span>Ativas inativas</span><strong>{{ $stats['inactive_active'] }}</strong><small>podem expirar</small></div>
                <div class="gs-stat"><span>Expiradas</span><strong>{{ $stats['expired'] }}</strong><small>status expired</small></div>
                <div class="gs-stat"><span>Fechadas</span><strong>{{ $stats['closed'] }}</strong><small>status closed</small></div>
                <div class="gs-stat"><span>Hoje</span><strong>{{ $stats['today'] }}</strong><small>criadas hoje</small></div>
                <div class="gs-stat"><span>Players ativos</span><strong>{{ $stats['unique_players_active'] }}</strong><small>usuários únicos</small></div>
            </div>
        </section>

        <section class="gs-card gs-panel">
            <div class="gs-toolbar">
                <div class="gs-search-row">
                    <input class="gs-search" type="search" placeholder="Buscar por ID, usuário, e-mail, CPF, jogo, provedor, dispositivo ou IP" wire:model.live.debounce.500ms="search">

                    <div class="gs-filter-tabs">
                        <button type="button" class="gs-filter {{ $this->filterButtonClass('all') }}" wire:click="setStatusFilter('all')">
                            Todas
                        </button>
                        <button type="button" class="gs-filter {{ $this->filterButtonClass(\App\Models\GameSession::STATUS_ACTIVE) }}" wire:click="setStatusFilter('{{ \App\Models\GameSession::STATUS_ACTIVE }}')">
                            Ativas
                        </button>
                        <button type="button" class="gs-filter {{ $this->filterButtonClass(\App\Models\GameSession::STATUS_EXPIRED) }}" wire:click="setStatusFilter('{{ \App\Models\GameSession::STATUS_EXPIRED }}')">
                            Expiradas
                        </button>
                        <button type="button" class="gs-filter {{ $this->filterButtonClass(\App\Models\GameSession::STATUS_CLOSED) }}" wire:click="setStatusFilter('{{ \App\Models\GameSession::STATUS_CLOSED }}')">
                            Fechadas
                        </button>
                    </div>
                </div>

                <div class="gs-actions">
                    <button class="gs-btn gs-btn-warning" type="button" wire:click="expireInactive">
                        Expirar inativas +5min
                    </button>

                    <button class="gs-btn gs-btn-danger" type="button" wire:click="deleteExpired" onclick="return confirm('Excluir todas as sessões expiradas?')">
                        Excluir expiradas
                    </button>

                    <button class="gs-btn gs-btn-danger" type="button" wire:click="deleteClosed" onclick="return confirm('Excluir todas as sessões fechadas?')">
                        Excluir fechadas
                    </button>
                </div>
            </div>

            <div class="gs-table-card">
                <div class="gs-table-head">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <h3>Lista de sessões</h3>
                        <span class="gs-count">{{ $sessions->count() }} exibidas</span>
                        <span class="gs-count">Filtro: {{ $statusFilter === 'all' ? 'Todas' : $this->statusLabel($statusFilter) }}</span>
                    </div>

                    <select class="gs-search" style="width:130px" wire:model.live="limit">
                        <option value="25">25 linhas</option>
                        <option value="50">50 linhas</option>
                        <option value="75">75 linhas</option>
                        <option value="100">100 linhas</option>
                    </select>
                </div>

                @include('filament.pages.partials.admin-game-sessions-table', [
                    'sessions' => $sessions,
                ])
            </div>
        </section>
    </div>

    @if($showDetailsModal && $selectedSession)
        <div class="gs-modal-backdrop" wire:click.self="closeDetails">
            <div class="gs-modal">
                <div class="gs-modal-head">
                    <div>
                        <h3>Sessão #{{ $selectedSession->id }}</h3>
                        <p>{{ $selectedSession->user?->name ?: 'Usuário removido' }} • {{ $selectedSession->user?->email ?: 'Sem e-mail' }}</p>
                    </div>

                    <button type="button" class="gs-close" wire:click="closeDetails">Fechar</button>
                </div>

                <div class="gs-modal-body">
                    <div class="gs-info-grid">
                        <div class="gs-info"><span>Status</span><strong>{{ $this->statusLabel($selectedSession->status) }}</strong></div>
                        <div class="gs-info"><span>Usuário ID</span><strong>{{ $selectedSession->user_id ?: '—' }}</strong></div>
                        <div class="gs-info"><span>Jogo</span><strong>{{ $this->gameName($selectedSession->game_id) }}</strong></div>
                        <div class="gs-info"><span>Game ID</span><strong>{{ $selectedSession->game_id ?: '—' }}</strong></div>
                        <div class="gs-info"><span>Provedor</span><strong>{{ $selectedSession->provider ?: '—' }}</strong></div>
                        <div class="gs-info"><span>Duração</span><strong>{{ $this->duration($selectedSession) }}</strong></div>
                        <div class="gs-info"><span>Início</span><strong>{{ $selectedSession->started_at?->format('d/m/Y H:i:s') ?: '—' }}</strong></div>
                        <div class="gs-info"><span>Último ping</span><strong>{{ $selectedSession->last_ping_at?->format('d/m/Y H:i:s') ?: '—' }}</strong></div>
                        <div class="gs-info"><span>Fechada em</span><strong>{{ $selectedSession->closed_at?->format('d/m/Y H:i:s') ?: '—' }}</strong></div>
                        <div class="gs-info"><span>Dispositivo</span><strong>{{ $selectedSession->device ?: '—' }}</strong></div>
                        <div class="gs-info"><span>IP</span><strong>{{ $selectedSession->ip ?: '—' }}</strong></div>
                        <div class="gs-info"><span>Atualizada</span><strong>{{ $selectedSession->updated_at?->format('d/m/Y H:i:s') ?: '—' }}</strong></div>
                    </div>

                    @if($selectedSession->status === \App\Models\GameSession::STATUS_ACTIVE)
                        <div class="gs-actions">
                            <button class="gs-btn gs-btn-warning" type="button" wire:click="disconnect({{ $selectedSession->id }})" onclick="return confirm('Desconectar esta sessão ativa?')">
                                Desconectar sessão
                            </button>
                        </div>
                    @else
                        <div class="gs-actions">
                            <button class="gs-btn gs-btn-danger" type="button" wire:click="deleteSession({{ $selectedSession->id }})" onclick="return confirm('Excluir esta sessão?')">
                                Excluir sessão
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament::page>