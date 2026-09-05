<x-filament::page>
    <style>
        .rf-wrap{display:grid;gap:18px}
        .rf-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(23, 23, 23,.94));box-shadow:0 18px 50px rgba(0,0,0,.18);overflow:hidden}
        .rf-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.20),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.16),transparent 30%)}
        .rf-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.035em}
        .rf-sub{margin:7px 0 0;color:#a3a3a3;font-size:13px;line-height:1.5}
        .rf-stats{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.rf-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .rf-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px}
        .rf-stat span{display:block;color:#737373;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .rf-stat strong{display:block;margin-top:5px;color:#fff;font-size:22px;font-weight:900}
        .rf-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid rgba(163, 163, 163,.14)}
        .rf-muted{color:#737373;font-size:12px}
        .rf-filter{padding:16px 18px;border-bottom:1px solid rgba(163, 163, 163,.12)}
        .rf-grid{display:grid;gap:12px}
        @media(min-width:900px){.rf-grid{grid-template-columns:1.15fr 1.15fr .8fr .8fr .8fr}}
        .rf-field label{display:block;margin-bottom:6px;color:#d4d4d4;font-size:12px;font-weight:800}
        .rf-field input,.rf-field select{width:100%;min-height:39px;border-radius:12px;border:1px solid rgba(163, 163, 163,.20);background:rgba(10, 10, 10,.34);color:#fff;padding:0 12px;font-size:13px}
        .rf-table-wrap{padding:16px 18px}
        .rf-table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid rgba(163, 163, 163,.14);border-radius:16px}
        .rf-table th{background:rgba(20, 20, 20,.85);color:#a3a3a3;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;padding:12px}
        .rf-table td{border-top:1px solid rgba(163, 163, 163,.10);color:#ffedd5;font-size:13px;padding:12px;vertical-align:middle}
        .rf-main{color:#fff;font-weight:800}
        .rf-small{display:block;margin-top:2px;color:#737373;font-size:11px}
        .rf-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:900}
        .rf-completed{background:rgba(34,197,94,.12);color:#86efac;border:1px solid rgba(34,197,94,.22)}
        .rf-pending{background:rgba(245,158,11,.12);color:#fcd34d;border:1px solid rgba(245,158,11,.22)}
        .rf-other{background:rgba(163, 163, 163,.12);color:#d4d4d4;border:1px solid rgba(163, 163, 163,.22)}
        .rf-remove{border:0;border-radius:12px;background:rgba(239,68,68,.18);color:#fecaca;padding:9px 11px;font-size:12px;font-weight:900;cursor:pointer}
        .rf-remove:hover{background:rgba(239,68,68,.28)}
        .rf-disabled{color:#737373;font-size:12px}
        .rf-empty{padding:42px;text-align:center;color:#a3a3a3}
        .rf-raw{margin:0 18px 18px;border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.42);padding:12px}
        .rf-raw summary{cursor:pointer;color:#fb923c;font-size:12px;font-weight:900}
        .rf-raw pre{margin:10px 0 0;max-height:340px;overflow:auto;white-space:pre-wrap;word-break:break-word;color:#d4d4d4;font-size:11px}
    </style>

    @php
        $items = $this->filteredItems();
        $counters = $this->counters();
    @endphp

    <div class="rf-wrap">
        <section class="rf-card">
            <div class="rf-hero">
                <h2 class="rf-title">Gestão de Rodadas Grátis</h2>
                <p class="rf-sub">
                    Liste, filtre e remova rodadas grátis pendentes na PlayFiver. Rodadas com status <strong>completed</strong> são histórico e não podem ser removidas.
                </p>
            </div>

            <div class="rf-stats">
                <div class="rf-stat"><span>Total retornado</span><strong>{{ $counters['total'] }}</strong></div>
                <div class="rf-stat"><span>Filtrados</span><strong>{{ $counters['filtered'] }}</strong></div>
                <div class="rf-stat"><span>Completed</span><strong>{{ $counters['completed'] }}</strong></div>
                <div class="rf-stat"><span>Removíveis</span><strong>{{ $counters['removable'] }}</strong></div>
            </div>
        </section>

        <section class="rf-card">
            <div class="rf-toolbar">
                <span class="rf-muted">Última consulta: {{ $lastLoadedAt ?: '-' }}</span>

                <x-filament::button wire:click="clearFilters" color="gray" size="sm" icon="heroicon-o-x-mark">
                    Limpar filtros
                </x-filament::button>

                <x-filament::button wire:click="loadFreeBonus" color="primary" size="sm" icon="heroicon-o-arrow-path">
                    Atualizar lista
                </x-filament::button>
            </div>

            <div class="rf-filter">
                <div class="rf-grid">
                    <div class="rf-field">
                        <label>E-mail / usuário</label>
                        <input wire:model.live.debounce.400ms="searchUser" type="text" placeholder="cliente@email.com">
                    </div>

                    <div class="rf-field">
                        <label>Jogo / código</label>
                        <input wire:model.live.debounce.400ms="searchGame" type="text" placeholder="Fortune Tiger ou 126">
                    </div>

                    <div class="rf-field">
                        <label>Status</label>
                        <select wire:model.live="statusFilter">
                            <option value="">Todos</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="rf-field">
                        <label>Criado de</label>
                        <input wire:model.live="createdFrom" type="date">
                    </div>

                    <div class="rf-field">
                        <label>Criado até</label>
                        <input wire:model.live="createdUntil" type="date">
                    </div>
                </div>
            </div>

            <div class="rf-table-wrap">
                <table class="rf-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Jogo</th>
                            <th>Rodadas</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th style="text-align:right">Ação</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($items as $item)
                            <tr wire:key="free-bonus-{{ $item['id'] }}">
                                <td>#{{ $item['id'] }}</td>

                                <td>
                                    <span class="rf-main">{{ $item['player'] }}</span>
                                </td>

                                <td>
                                    <span class="rf-main">{{ $item['game_name'] }}</span>
                                    <span class="rf-small">Código: {{ $item['game_code'] }}</span>
                                </td>

                                <td>
                                    <span class="rf-main">{{ $item['rounds'] }} restantes</span>
                                    <span class="rf-small">{{ $item['used_rounds'] }} usadas de {{ $item['total_rounds'] }}</span>
                                </td>

                                <td>
                                    <span @class([
                                        'rf-badge',
                                        'rf-completed' => $item['status_normalized'] === 'completed',
                                        'rf-pending' => $item['status_normalized'] === 'pending',
                                        'rf-other' => ! in_array($item['status_normalized'], ['completed', 'pending'], true),
                                    ])>
                                        {{ $item['status'] }}
                                    </span>
                                </td>

                                <td>{{ $item['created_at_br'] }}</td>

                                <td style="text-align:right">
                                    @if ($item['removable'])
                                        <x-filament::modal id="remove-free-bonus-{{ $item['id'] }}" width="md">
                                            <x-slot name="trigger">
                                                <button type="button" class="rf-remove">
                                                    Remover do cliente
                                                </button>
                                            </x-slot>

                                            <x-slot name="heading">
                                                Remover rodadas grátis?
                                            </x-slot>

                                            <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                                                <p>
                                                    Isso vai remover as rodadas grátis pendentes deste cliente na PlayFiver.
                                                </p>

                                                <div class="rounded-xl bg-gray-50 p-3 text-xs dark:bg-gray-800">
                                                    <div><strong>Cliente:</strong> {{ $item['player'] }}</div>
                                                    <div><strong>Jogo:</strong> {{ $item['game_name'] }} / {{ $item['game_code'] }}</div>
                                                    <div><strong>Rodadas restantes:</strong> {{ $item['rounds'] }}</div>
                                                </div>
                                            </div>

                                            <x-slot name="footer">
                                                <x-filament::button
                                                    color="gray"
                                                    x-on:click="$dispatch('close-modal', { id: 'remove-free-bonus-{{ $item['id'] }}' })"
                                                >
                                                    Cancelar
                                                </x-filament::button>

                                                <x-filament::button
                                                    color="danger"
                                                    wire:click="removeFreeBonus({{ (int) $item['id'] }})"
                                                    x-on:click="$dispatch('close-modal', { id: 'remove-free-bonus-{{ $item['id'] }}' })"
                                                >
                                                    Confirmar remoção
                                                </x-filament::button>
                                            </x-slot>
                                        </x-filament::modal>
                                    @else
                                        <span class="rf-disabled">
                                            @if ($item['status_normalized'] === 'completed')
                                                Completed — histórico
                                            @else
                                                Não removível
                                            @endif
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="rf-empty">
                                    Nenhuma rodada grátis encontrada com os filtros atuais.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (! empty($items))
                <details class="rf-raw">
                    <summary>Ver retorno bruto da PlayFiver</summary>
                    <pre>{{ json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            @endif
        </section>
    </div>
</x-filament::page>