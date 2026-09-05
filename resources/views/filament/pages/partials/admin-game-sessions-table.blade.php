@if($sessions->count() > 0)
    <div class="gs-table-wrap">
        <table class="gs-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuário</th>
                    <th>Jogo</th>
                    <th>Provedor</th>
                    <th>Status</th>
                    <th>Dispositivo/IP</th>
                    <th>Início</th>
                    <th>Último ping</th>
                    <th>Duração</th>
                    <th>Ações</th>
                </tr>
            </thead>

            <tbody>
                @foreach($sessions as $session)
                    <tr>
                        <td>{{ $session->id }}</td>

                        <td>
                            <div class="gs-user">
                                <strong>{{ $session->user?->name ?: 'Usuário removido' }}</strong>
                                <span>{{ $session->user?->email ?: 'Sem e-mail' }}</span>
                            </div>
                        </td>

                        <td>
                            <strong style="color:#fff">{{ $this->gameName($session->game_id) }}</strong>
                            <div style="color:#737373;font-size:11px">ID {{ $session->game_id ?: '—' }}</div>
                        </td>

                        <td>{{ $session->provider ?: '—' }}</td>

                        <td>
                            <span class="gs-badge {{ $this->statusClass($session->status) }}">
                                {{ $this->statusLabel($session->status) }}
                            </span>
                        </td>

                        <td>
                            <div>{{ $session->device ?: '—' }}</div>
                            <div style="color:#737373;font-size:11px">{{ $session->ip ?: '—' }}</div>
                        </td>

                        <td>{{ $session->started_at?->format('d/m/Y H:i') ?: '—' }}</td>
                        <td>{{ $session->last_ping_at?->format('d/m/Y H:i') ?: '—' }}</td>
                        <td>{{ $this->duration($session) }}</td>

                        <td>
                            <div class="gs-actions">
                                <button class="gs-btn gs-btn-primary" type="button" wire:click="showDetails({{ $session->id }})">
                                    Detalhes
                                </button>

                                @if($session->status === \App\Models\GameSession::STATUS_ACTIVE)
                                    <button class="gs-btn gs-btn-warning" type="button" wire:click="disconnect({{ $session->id }})" onclick="return confirm('Desconectar este jogador?')">
                                        Desconectar
                                    </button>
                                @else
                                    <button class="gs-btn gs-btn-danger" type="button" wire:click="deleteSession({{ $session->id }})" onclick="return confirm('Excluir esta sessão?')">
                                        Excluir
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="gs-empty">
        Nenhuma sessão encontrada para o filtro atual.
    </div>
@endif