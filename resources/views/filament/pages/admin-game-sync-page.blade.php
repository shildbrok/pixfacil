<x-filament::page>
    <style>
        .sg-wrap{display:grid;gap:18px}
        .sg-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .sg-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.20),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.15),transparent 32%)}
        .sg-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .sg-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:1020px}
        .sg-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .sg-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.sg-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.sg-stats{grid-template-columns:repeat(8,minmax(0,1fr))}}
        .sg-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .sg-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .sg-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .sg-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .sg-panel{padding:16px;display:grid;gap:14px}
        .sg-controls{display:grid;gap:12px}
        @media(min-width:1000px){.sg-controls{grid-template-columns:1.2fr 1fr 1fr}}
        .sg-box{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:14px;display:grid;gap:10px}
        .sg-box h3{margin:0;color:#fff;font-size:14px;font-weight:950}
        .sg-box p{margin:0;color:#a3a3a3;font-size:12px;line-height:1.45}
        .sg-input,.sg-select{width:100%;border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:#0a0a0a;color:#fff;padding:10px 12px;font-size:13px}
        .sg-checkbox{display:flex;align-items:flex-start;gap:9px;color:#d4d4d4;font-size:12px;line-height:1.4}
        .sg-actions{display:flex;gap:10px;flex-wrap:wrap}
        .sg-btn{border:0;border-radius:12px;color:#fff;padding:10px 14px;font-size:13px;font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
        .sg-btn-primary{background:#ea580c}
        .sg-btn-success{background:#16a34a}
        .sg-btn-warning{background:#d97706}
        .sg-btn-gray{background:#404040}
        .sg-btn-danger{background:#dc2626}
        .sg-btn:disabled{opacity:.55;cursor:not-allowed}
        .sg-remote{display:grid;gap:10px}
        @media(min-width:900px){.sg-remote{grid-template-columns:repeat(6,minmax(0,1fr))}}
        .sg-table-wrap{overflow:hidden;border:1px solid rgba(163, 163, 163,.14);border-radius:18px;background:rgba(10, 10, 10,.26)}
        .sg-table-scroll{overflow-x:auto}
        .sg-table{width:100%;border-collapse:collapse;min-width:980px}
        .sg-table th{background:rgba(20, 20, 20,.82);color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;text-align:left;padding:12px;border-bottom:1px solid rgba(163, 163, 163,.14)}
        .sg-table td{color:#d4d4d4;font-size:13px;padding:12px;border-bottom:1px solid rgba(163, 163, 163,.10);vertical-align:middle}
        .sg-provider{display:flex;align-items:center;gap:10px}
        .sg-provider img{width:42px;height:42px;border-radius:12px;object-fit:contain;background:#0a0a0a;border:1px solid rgba(163, 163, 163,.14)}
        .sg-badge{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:900;border:1px solid rgba(163, 163, 163,.16)}
        .sg-badge-ok{background:rgba(34,197,94,.12);color:#22c55e;border-color:rgba(34,197,94,.25)}
        .sg-badge-warn{background:rgba(245,158,11,.12);color:#fbbf24;border-color:rgba(245,158,11,.25)}
        .sg-badge-bad{background:rgba(239,68,68,.12);color:#f87171;border-color:rgba(239,68,68,.25)}
        .sg-results{display:grid;gap:10px}
        @media(min-width:900px){.sg-results{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .sg-error{border:1px solid rgba(239,68,68,.28);border-radius:16px;background:rgba(127,29,29,.25);padding:12px;color:#fecaca;font-size:12px;line-height:1.5}
    </style>

    @php($stats = $this->stats())
    @php($remote = $this->remoteStats())

    <div class="sg-wrap">
        <section class="sg-card">
            <div class="sg-hero">
                <h2 class="sg-title">Sincronização PlayFiver</h2>
                <p class="sg-sub">
                    Consulte provedores remotos, selecione o que deseja importar e sincronize provedores/jogos do agregador com o catálogo local.
                </p>

                <div class="sg-note">
                    Esta tela usa <strong>PlayFiverCatalogSyncService</strong>. A sincronização cria provedores, importa jogos faltantes e pode atualizar game_code por nome quando necessário.
                </div>
            </div>

            <div class="sg-stats">
                <div class="sg-stat"><span>Provedores</span><strong>{{ $stats['providers_total'] }}</strong><small>Total local</small></div>
                <div class="sg-stat"><span>PlayFiver</span><strong>{{ $stats['providers_play_fiver'] }}</strong><small>distribution</small></div>
                <div class="sg-stat"><span>Jogos</span><strong>{{ $stats['games_total'] }}</strong><small>Total local</small></div>
                <div class="sg-stat"><span>Jogos PF</span><strong>{{ $stats['games_play_fiver'] }}</strong><small>play_fiver</small></div>
                <div class="sg-stat"><span>Ativos</span><strong>{{ $stats['games_active'] }}</strong><small>status 1</small></div>
                <div class="sg-stat"><span>Home</span><strong>{{ $stats['games_home'] }}</strong><small>show_home</small></div>
                <div class="sg-stat"><span>Destaque</span><strong>{{ $stats['games_featured'] }}</strong><small>is_featured</small></div>
                <div class="sg-stat"><span>Último jogo</span><strong style="font-size:12px">{{ $stats['last_game_update'] ? \Carbon\Carbon::parse($stats['last_game_update'])->format('d/m/Y H:i') : '-' }}</strong><small>games.updated_at</small></div>
            </div>
        </section>

        <section class="sg-card sg-panel">
            <div class="sg-controls">
                <div class="sg-box">
                    <h3>1. Configuração de importação</h3>
                    <p>Escolha o tipo padrão para jogos novos e defina se registros existentes devem ser atualizados.</p>

                    <select class="sg-select" wire:model="importGameType">
                        <option value="Slots">Slots</option>
                        <option value="Live">Live</option>
                        <option value="Crash">Crash</option>
                        <option value="Lottery">Lottery</option>
                    </select>

                    <label class="sg-checkbox">
                        <input type="checkbox" wire:model="updateExistingProviders">
                        <span>Atualizar dados de provedores já existentes</span>
                    </label>

                    <label class="sg-checkbox">
                        <input type="checkbox" wire:model="updateExistingGames">
                        <span>Atualizar jogos já existentes durante a sincronização</span>
                    </label>
                </div>

                <div class="sg-box">
                    <h3>2. Consultar PlayFiver</h3>
                    <p>Carrega provedores remotos antes de selecionar o que será importado.</p>

                    <div class="sg-actions">
                        <button class="sg-btn sg-btn-primary" type="button" wire:click="loadProviders" wire:loading.attr="disabled">
                            Consultar provedores
                        </button>
                    </div>

                    <p>Última ação: <strong style="color:#fff">{{ $lastActionAt ?: '-' }}</strong></p>
                </div>

                <div class="sg-box">
                    <h3>3. Ações</h3>
                    <p>Sincronize os selecionados ou apenas atualize game_code por nome.</p>

                    <div class="sg-actions">
                        <button class="sg-btn sg-btn-success" type="button" wire:click="syncSelected" wire:loading.attr="disabled">
                            Sincronizar selecionados
                        </button>

                        <button class="sg-btn sg-btn-warning" type="button" wire:click="updateGameCodes" wire:loading.attr="disabled">
                            Atualizar game code
                        </button>
                    </div>
                </div>
            </div>

            <div class="sg-remote">
                <div class="sg-stat"><span>Remotos carregados</span><strong>{{ $remote['loaded'] }}</strong><small>Consulta atual</small></div>
                <div class="sg-stat"><span>Selecionados</span><strong>{{ $remote['selected'] }}</strong><small>Para ação</small></div>
                <div class="sg-stat"><span>Já existem</span><strong>{{ $remote['already_exists'] }}</strong><small>Local PF</small></div>
                <div class="sg-stat"><span>Novos</span><strong>{{ $remote['new'] }}</strong><small>A importar</small></div>
                <div class="sg-stat"><span>Ativos remoto</span><strong>{{ $remote['remote_active'] }}</strong><small>Status PF</small></div>
                <div class="sg-stat"><span>Jogos locais</span><strong>{{ $remote['local_games'] }}</strong><small>Desses provedores</small></div>
            </div>

            @if($lastSyncResult)
                <div class="sg-results">
                    <div class="sg-stat"><span>Provedores criados</span><strong>{{ $lastSyncResult['providers_created'] ?? 0 }}</strong><small>Última sync</small></div>
                    <div class="sg-stat"><span>Provedores atualizados</span><strong>{{ $lastSyncResult['providers_updated'] ?? 0 }}</strong><small>Última sync</small></div>
                    <div class="sg-stat"><span>Jogos criados</span><strong>{{ $lastSyncResult['games_created'] ?? 0 }}</strong><small>Última sync</small></div>
                    <div class="sg-stat"><span>Jogos atualizados</span><strong>{{ $lastSyncResult['games_updated'] ?? 0 }}</strong><small>Última sync</small></div>
                </div>
            @endif

            @if($lastCodeUpdateResult)
                <div class="sg-results">
                    <div class="sg-stat"><span>Provedores verificados</span><strong>{{ $lastCodeUpdateResult['providers_checked'] ?? 0 }}</strong><small>Game code</small></div>
                    <div class="sg-stat"><span>Jogos atualizados</span><strong>{{ $lastCodeUpdateResult['games_updated'] ?? 0 }}</strong><small>Game code</small></div>
                    <div class="sg-stat"><span>Mesmo código</span><strong>{{ $lastCodeUpdateResult['games_skipped_same_code'] ?? 0 }}</strong><small>Ignorados</small></div>
                    <div class="sg-stat"><span>Nome não encontrado</span><strong>{{ $lastCodeUpdateResult['games_name_not_found'] ?? 0 }}</strong><small>Revisar</small></div>
                </div>
            @endif

            @if($this->hasSyncErrors())
                <div class="sg-error">
                    <strong>Erros encontrados:</strong>
                    <ul style="margin:8px 0 0;padding-left:18px">
                        @foreach($this->syncErrors() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <section class="sg-card sg-panel">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between">
                <div>
                    <h3 style="margin:0;color:#fff;font-size:16px;font-weight:950">Provedores remotos</h3>
                    <p style="margin:4px 0 0;color:#a3a3a3;font-size:12px">Selecione os provedores antes de sincronizar.</p>
                </div>

                <div class="sg-actions">
                    <button class="sg-btn sg-btn-gray" type="button" wire:click="selectAllProviders">Selecionar todos</button>
                    <button class="sg-btn sg-btn-gray" type="button" wire:click="selectOnlyNewProviders">Selecionar novos</button>
                    <button class="sg-btn sg-btn-danger" type="button" wire:click="clearSelection">Limpar seleção</button>
                </div>
            </div>

            @if(count($providersPreview) > 0)
                <div class="sg-table-wrap">
                    <div class="sg-table-scroll">
                        <table class="sg-table">
                            <thead>
                                <tr>
                                    <th>Selecionar</th>
                                    <th>ID remoto</th>
                                    <th>Código</th>
                                    <th>Provedor</th>
                                    <th>Carteira</th>
                                    <th>Existe local</th>
                                    <th>Jogos locais</th>
                                    <th>Status remoto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($providersPreview as $provider)
                                    <tr>
                                        <td>
                                            <input type="checkbox" wire:model="selectedProviders" value="{{ $provider['remote_id'] }}">
                                        </td>
                                        <td>{{ $provider['remote_id'] }}</td>
                                        <td><strong style="color:#fff">{{ $provider['code'] }}</strong></td>
                                        <td>
                                            <div class="sg-provider">
                                                @if(!empty($provider['image_url']))
                                                    <img src="{{ $provider['image_url'] }}" alt="{{ $provider['remote_name'] }}">
                                                @endif
                                                <div>
                                                    <div style="color:#fff;font-weight:900">{{ $provider['remote_name'] }}</div>
                                                    <div style="color:#737373;font-size:11px">PlayFiver</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $provider['wallet_name'] ?: '-' }}</td>
                                        <td>
                                            @if($provider['local_provider_exists'])
                                                <span class="sg-badge sg-badge-ok">Sim</span>
                                            @else
                                                <span class="sg-badge sg-badge-warn">Não</span>
                                            @endif
                                        </td>
                                        <td>{{ $provider['local_games_count'] }}</td>
                                        <td>
                                            @if($provider['status'])
                                                <span class="sg-badge sg-badge-ok">Ativo</span>
                                            @else
                                                <span class="sg-badge sg-badge-bad">Inativo</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="sg-box">
                    <h3>Nenhum provedor carregado</h3>
                    <p>Clique em <strong>Consultar provedores</strong> para buscar a lista remota na PlayFiver.</p>
                </div>
            @endif
        </section>
    </div>
</x-filament::page>
