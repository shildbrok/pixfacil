<x-filament::page>
    <style>
        .st-wrap{display:grid;gap:18px}
        .st-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .st-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.20),transparent 34%),radial-gradient(circle at top right,rgba(245,158,11,.14),transparent 32%)}
        .st-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .st-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:1020px}
        .st-note{margin-top:12px;border:1px solid rgba(245,158,11,.25);border-radius:16px;background:rgba(120,53,15,.22);padding:12px;color:#fde68a;font-size:12px;line-height:1.55}
        .st-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.st-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1400px){.st-stats{grid-template-columns:repeat(12,minmax(0,1fr))}}
        .st-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .st-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .st-stat strong{display:block;margin-top:4px;color:#fff;font-size:16px;font-weight:950;word-break:break-all}
        .st-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .st-panel{padding:16px;display:grid;gap:14px}
        .st-grid{display:grid;gap:12px}
        @media(min-width:1000px){.st-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .st-box{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:14px;display:grid;gap:10px}
        .st-box h3{margin:0;color:#fff;font-size:14px;font-weight:950}
        .st-box p{margin:0;color:#a3a3a3;font-size:12px;line-height:1.45}
        .st-input{width:100%;border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:#0a0a0a;color:#fff;padding:10px 12px;font-size:13px}
        .st-btn{border:0;border-radius:12px;color:#fff;padding:10px 14px;font-size:13px;font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;width:max-content}
        .st-btn-primary{background:#ea580c}
        .st-btn-success{background:#16a34a}
        .st-btn-warning{background:#d97706}
        .st-btn-danger{background:#dc2626}
        .st-btn-gray{background:#404040}
        .st-btn:disabled{opacity:.55;cursor:not-allowed}
        .st-report{border:1px solid rgba(34,197,94,.22);border-radius:18px;background:rgba(5,46,22,.22);padding:14px;color:#dcfce7}
        .st-report h3{margin:0 0 8px;color:#fff;font-size:15px;font-weight:950}
        .st-code{overflow:auto;max-height:420px;border-radius:14px;background:#0a0a0a;border:1px solid rgba(163, 163, 163,.16);padding:12px;color:#d4d4d4;font-size:12px;line-height:1.45}

        .st-modal-backdrop{position:fixed;inset:0;z-index:80;background:rgba(10, 10, 10,.78);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .st-modal{width:min(470px,96vw);border:1px solid rgba(163, 163, 163,.22);border-radius:22px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45);overflow:hidden}
        .st-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16)}
        .st-modal-head h3{margin:0;color:#fff;font-size:17px;font-weight:950}
        .st-modal-head p{margin:6px 0 0;color:#a3a3a3;font-size:12px;line-height:1.45}
        .st-modal-body{padding:18px 20px;display:grid;gap:12px}
        .st-modal-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}

    </style>

    @php($stats = $this->stats())

    <div class="st-wrap">
        <section class="st-card">
            <div class="st-hero">
                <h2 class="st-title">Ferramentas do Sistema</h2>
                <p class="st-sub">
                    Execute rotinas administrativas de manutenção, limpeza de cache, recompilação, sessões, filas e storage.
                </p>

                <div class="st-note">
                    Ações sensíveis exigem PIN administrativo. Use limpeza de sessões e filas com cuidado, porque usuários conectados podem ser desconectados e workers podem reiniciar.
                </div>
            </div>

            <div class="st-stats">
                <div class="st-stat"><span>Ambiente</span><strong>{{ $stats['app_env'] }}</strong><small>{{ $stats['app_debug'] ? 'debug ativo' : 'debug off' }}</small></div>
                <div class="st-stat"><span>Cache</span><strong>{{ $stats['cache_driver'] }}</strong><small>driver</small></div>
                <div class="st-stat"><span>Sessão</span><strong>{{ $stats['session_driver'] }}</strong><small>driver</small></div>
                <div class="st-stat"><span>Fila</span><strong>{{ $stats['queue_driver'] }}</strong><small>driver</small></div>
                <div class="st-stat"><span>Storage</span><strong>{{ $stats['storage_link'] ? 'OK' : 'Pendente' }}</strong><small>public/storage</small></div>
                <div class="st-stat"><span>Asset version</span><strong style="font-size:11px">{{ $stats['asset_version'] ?: '-' }}</strong><small>cache</small></div>
                <div class="st-stat"><span>Views</span><strong>{{ $stats['views_count'] }}</strong><small>compiladas</small></div>
                <div class="st-stat"><span>Cache files</span><strong>{{ $stats['cache_files_count'] }}</strong><small>storage/framework</small></div>
                <div class="st-stat"><span>Sessões</span><strong>{{ $stats['session_files_count'] }}</strong><small>arquivos</small></div>
                <div class="st-stat"><span>Bootstrap</span><strong>{{ $stats['bootstrap_cache_count'] }}</strong><small>cache</small></div>
                <div class="st-stat"><span>Logs</span><strong>{{ $stats['log_size'] }}</strong><small>storage/logs</small></div>
                <div class="st-stat"><span>Última ação</span><strong style="font-size:12px">{{ $lastActionAt ?: '-' }}</strong><small>{{ $lastAction ?: 'nenhuma' }}</small></div>
            </div>
        </section>

        <section class="st-card st-panel">
<div class="st-grid">
                <div class="st-box">
                    <h3>Limpar cache da aplicação</h3>
                    <p>Executa CacheNuker com limpeza profunda, sem derrubar sessões e sem limpar filas.</p>
                    <button class="st-btn st-btn-danger" type="button" wire:click="requestAction('clear_application_cache')" wire:loading.attr="disabled">
                        Limpar cache
                    </button>
                </div>

                <div class="st-box">
                    <h3>Limpeza avançada</h3>
                    <p>Limpa caches profundos, views, bootstrap/cache, filas suportadas e renova asset_version.</p>
                    <button class="st-btn st-btn-warning" type="button" wire:click="requestAction('clear_advanced')" wire:loading.attr="disabled">
                        Limpeza avançada
                    </button>
                </div>

                <div class="st-box">
                    <h3>Limpar sessões</h3>
                    <p>Remove sessões conforme o driver atual. Pode deslogar usuários conectados.</p>
                    <button class="st-btn st-btn-warning" type="button" wire:click="requestAction('clear_sessions')" wire:loading.attr="disabled">
                        Limpar sessões
                    </button>
                </div>

                <div class="st-box">
                    <h3>Optimize clear</h3>
                    <p>Executa optimize:clear, cache:clear, config:clear, route:clear, view:clear e event:clear.</p>
                    <button class="st-btn st-btn-gray" type="button" wire:click="requestAction('run_optimize_clear')" wire:loading.attr="disabled">
                        Executar optimize:clear
                    </button>
                </div>

                <div class="st-box">
                    <h3>Recriar caches</h3>
                    <p>Recompila config, rotas, views, eventos e roda optimize.</p>
                    <button class="st-btn st-btn-primary" type="button" wire:click="requestAction('rebuild_caches')" wire:loading.attr="disabled">
                        Recriar caches
                    </button>
                </div>

                <div class="st-box">
                    <h3>Storage link</h3>
                    <p>Executa storage:link para corrigir imagens públicas em /storage.</p>
                    <button class="st-btn st-btn-success" type="button" wire:click="requestAction('create_storage_link')" wire:loading.attr="disabled">
                        Verificar storage link
                    </button>
                </div>

                <div class="st-box">
                    <h3>Reiniciar filas</h3>
                    <p>Executa queue:restart. Workers ativos reiniciam de forma segura no próximo ciclo.</p>
                    <button class="st-btn st-btn-warning" type="button" wire:click="requestAction('restart_queue')" wire:loading.attr="disabled">
                        Reiniciar workers
                    </button>
                </div>
            </div>

            @if($lastReport)
                <div class="st-report">
                    <h3>{{ $lastAction }} — {{ $lastActionAt }}</h3>
                    <div class="st-code">
                        <pre>{{ json_encode($lastReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            @endif
        </section>
    </div>

    @if($showPinModal)
        <div class="st-modal-backdrop" wire:click.self="closePinModal">
            <div class="st-modal">
                <div class="st-modal-head">
                    <h3>{{ $this->pendingActionTitle() }}</h3>
                    <p>{{ $this->pendingActionDescription() }}</p>
                </div>

                <div class="st-modal-body">
                    <input
                        class="st-input"
                        type="password"
                        inputmode="numeric"
                        placeholder="PIN administrativo"
                        wire:model.defer="adminPin"
                        wire:keydown.enter="confirmAndRun"
                        autofocus
                    >

                    <div class="st-modal-actions">
                        <button class="st-btn st-btn-gray" type="button" wire:click="closePinModal">
                            Cancelar
                        </button>

                        <button
                            class="st-btn {{ $this->pendingActionIsDanger() ? 'st-btn-danger' : 'st-btn-primary' }}"
                            type="button"
                            wire:click="confirmAndRun"
                            wire:loading.attr="disabled"
                        >
                            Confirmar e executar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</x-filament::page>