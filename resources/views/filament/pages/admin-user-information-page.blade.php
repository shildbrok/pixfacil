<x-filament::page>
    <style>
        .ud-wrap{display:grid;gap:18px}
        .ud-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .ud-hero{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.14),transparent 32%)}
        .ud-title{margin:0;color:#fff;font-size:24px;font-weight:950;letter-spacing:-.04em}
        .ud-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .ud-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
        .ud-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:13px;padding:10px 12px;background:rgba(249, 115, 22,.16);border:1px solid rgba(251, 146, 60,.24);color:#ffedd5;font-size:12px;font-weight:900;text-decoration:none}
        .ud-btn.gray{background:rgba(163, 163, 163,.12);border-color:rgba(163, 163, 163,.22);color:#e5e7eb}
        .ud-badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:13px}
        .ud-badge{border:1px solid rgba(163, 163, 163,.2);border-radius:999px;background:rgba(20, 20, 20,.52);padding:7px 10px;color:#e5e7eb;font-size:12px;font-weight:800}
        .ud-nav{display:flex;flex-wrap:wrap;gap:8px;padding:14px 18px;border-top:1px solid rgba(163, 163, 163,.12);background:rgba(10, 10, 10,.16)}
        .ud-nav a{border:1px solid rgba(163, 163, 163,.16);border-radius:999px;padding:8px 11px;color:#d4d4d4;text-decoration:none;font-size:12px;font-weight:900}
        .ud-stats{display:grid;gap:10px;padding:0 18px 18px}
        @media(min-width:900px){.ud-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(min-width:1300px){.ud-stats{grid-template-columns:repeat(6,minmax(0,1fr))}}
        .ud-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .ud-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .ud-stat strong{display:block;margin-top:4px;color:#fff;font-size:16px;font-weight:950}
        .ud-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .ud-grid{display:grid;gap:16px}
        @media(min-width:1150px){.ud-grid.two{grid-template-columns:1fr 1fr}}
        .ud-section{padding:16px 18px}
        .ud-section h3{margin:0 0 12px;color:#fff;font-size:16px;font-weight:900}
        .ud-list{display:grid;gap:8px}
        .ud-row{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;border:1px solid rgba(163, 163, 163,.12);border-radius:13px;background:rgba(20, 20, 20,.42);padding:10px 12px}
        .ud-row strong{display:block;color:#fff;font-size:13px}
        .ud-row small{display:block;margin-top:2px;color:#a3a3a3;font-size:11px}
        .ud-row em{font-style:normal;color:#e5e7eb;font-weight:900;font-size:12px;text-align:right}
        .ud-table-wrap{overflow:auto;max-height:560px;border-radius:14px;border:1px solid rgba(163, 163, 163,.12)}
        .ud-table{width:100%;border-collapse:separate;border-spacing:0;min-width:760px}
        .ud-table th{position:sticky;top:0;background:rgba(20, 20, 20,.95);color:#a3a3a3;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;padding:10px;z-index:1}
        .ud-table td{border-top:1px solid rgba(163, 163, 163,.10);color:#ffedd5;font-size:12px;padding:10px;vertical-align:top}
        .ud-note{margin-top:9px;color:#a3a3a3;font-size:11px}
    </style>

    @php($stats = $this->stats())
    @php($wallet = $this->walletData())
    @php($counts = $this->historyCounts())

    <div class="ud-wrap">
        <section class="ud-card">
            <div class="ud-hero">
                <div>
                    <h2 class="ud-title">{{ $this->user->name ?: $this->user->email }}</h2>
                    <p class="ud-sub">
                        {{ $this->user->email }} • CPF: {{ $this->user->cpf ?: '-' }} • Telefone: {{ $this->user->phone ?: '-' }}
                    </p>
                    <div class="ud-badges">
                        <span class="ud-badge">Perfil: {{ $this->profileLabel() }}</span>
                        <span class="ud-badge">Cadastro: {{ $this->user->created_at?->format('d/m/Y H:i') }}</span>
                        <span class="ud-badge">Status: {{ $this->user->status }}</span>
                        @if($this->user->is_influencer)<span class="ud-badge">Influenciador</span>@endif
                        @if($this->user->inviter_code)<span class="ud-badge">Afiliado: {{ $this->user->inviter_code }}</span>@endif
                    </div>
                </div>
                <div class="ud-actions">
                    <a class="ud-btn gray" href="{{ \App\Filament\Pages\AdminUsersPage::getUrl() }}">Voltar para usuários</a>
                </div>
            </div>

            <div class="ud-nav">
                <a href="#resumo">Resumo</a>
                <a href="#carteiras">Carteiras</a>
                <a href="#apostas">Histórico de apostas</a>
                <a href="#depositos">Histórico de depósitos</a>
                <a href="#saques">Histórico de saques</a>
                <a href="#indicacoes">Afiliado / indicações</a>
            </div>

            <div id="resumo" class="ud-stats" style="padding-top:18px">
                <div class="ud-stat"><span>Saldo total</span><strong>{{ $this->money($wallet['total']) }}</strong><small>Carteiras disponíveis</small></div>
                <div class="ud-stat"><span>Depositado</span><strong>{{ $this->money($stats['total_deposits']) }}</strong><small>{{ $counts['deposits'] }} registros</small></div>
                <div class="ud-stat"><span>Sacado</span><strong>{{ $this->money($stats['total_withdrawals']) }}</strong><small>{{ $counts['withdrawals'] }} registros</small></div>
                <div class="ud-stat"><span>Apostado/perdas</span><strong>{{ $this->money($stats['total_bet']) }}</strong><small>{{ $stats['bet_count'] }} apostas</small></div>
                <div class="ud-stat"><span>Ganhos</span><strong>{{ $this->money($stats['total_win']) }}</strong><small>{{ $stats['win_count'] }} vitórias</small></div>
                <div class="ud-stat"><span>Resultado casa</span><strong>{{ $this->money($stats['house_result']) }}</strong><small>Apostado - ganho</small></div>
            </div>
        </section>

        <div class="ud-grid two">
            <section id="carteiras" class="ud-card ud-section">
                <h3>Carteiras</h3>
                <div class="ud-list">
                    <div class="ud-row"><div><strong>Carteira de saque</strong><small>balance_withdrawal</small></div><em>{{ $this->money($wallet['balance_withdrawal']) }}</em></div>
                    <div class="ud-row"><div><strong>Carteira de depósito</strong><small>balance</small></div><em>{{ $this->money($wallet['balance']) }}</em></div>
                    <div class="ud-row"><div><strong>Carteira de bônus</strong><small>balance_bonus</small></div><em>{{ $this->money($wallet['balance_bonus']) }}</em></div>
                    <div class="ud-row"><div><strong>Rollover depósito</strong><small>balance_deposit_rollover</small></div><em>{{ $this->money($wallet['balance_deposit_rollover']) }}</em></div>
                    <div class="ud-row"><div><strong>Rollover bônus</strong><small>balance_bonus_rollover</small></div><em>{{ $this->money($wallet['balance_bonus_rollover']) }}</em></div>
                    <div class="ud-row"><div><strong>Comissão afiliado</strong><small>refer_rewards</small></div><em>{{ $this->money($wallet['refer_rewards']) }}</em></div>
                </div>
            </section>

            <section class="ud-card ud-section">
                <h3>Resumo do afiliado</h3>
                <div class="ud-stats" style="padding:0;grid-template-columns:repeat(2,minmax(0,1fr))">
                    <div class="ud-stat">
                        <span>Total indicado</span>
                        <strong>{{ $stats['affiliate_clients'] }}</strong>
                        <small>Usuários vinculados a este afiliado</small>
                    </div>

                    <div class="ud-stat">
                        <span>Indicados depositantes</span>
                        <strong>{{ $stats['affiliate_depositors'] }}</strong>
                        <small>Indicados que bateram regra de depósito</small>
                    </div>

                    <div class="ud-stat">
                        <span>Depósitos dos indicados</span>
                        <strong>{{ $this->money($stats['affiliate_real_deposits']) }}</strong>
                        <small>Total pago pelos usuários indicados</small>
                    </div>

                    <div class="ud-stat">
                        <span>CPA pago</span>
                        <strong>{{ $this->money($stats['affiliate_commission']) }}</strong>
                        <small>Comissões já registradas</small>
                    </div>

                    <div class="ud-stat">
                        <span>CPA a pagar</span>
                        <strong>{{ $this->money($stats['affiliate_payable']) }}</strong>
                        <small>Saldo de comissão na carteira</small>
                    </div>

                    <div class="ud-stat">
                        <span>Lucro trazido</span>
                        <strong>{{ $this->money($stats['affiliate_house_profit']) }}</strong>
                        <small>Apostas dos indicados - ganhos pagos</small>
                    </div>
                </div>
            </section>
        </div>

        <section id="apostas" class="ud-card ud-section">
            <h3>Histórico de apostas</h3>
            <div class="ud-table-wrap">
                <table class="ud-table">
                    <thead><tr><th>Jogo</th><th>Resultado</th><th>Carteira</th><th>Valor</th><th>Agregador</th><th>Estornado</th><th>Data</th></tr></thead>
                    <tbody>
                        @forelse($this->bets() as $bet)
                            <tr>
                                <td>{{ $bet['game'] }}<br><small>{{ $bet['round_id'] ?: '' }}</small></td>
                                <td>{{ $this->resultLabel($bet['type']) }}</td>
                                <td>{{ $this->walletLabel($bet['wallet']) }}</td>
                                <td>{{ $this->money($bet['amount']) }}</td>
                                <td>{{ $bet['provider'] ?: '-' }}</td>
                                <td>{{ $bet['refunded'] ? 'Sim' : 'Não' }}</td>
                                <td>{{ $bet['created_at'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">Nenhuma aposta encontrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="ud-note">Mostrando até 300 registros recentes para manter a página leve.</p>
        </section>

        <div class="ud-grid two">
            <section id="depositos" class="ud-card ud-section">
                <h3>Histórico de depósitos</h3>
                <div class="ud-table-wrap">
                    <table class="ud-table">
                        <thead><tr><th>Valor</th><th>Status</th><th>Tipo</th><th>Transação</th><th>Data</th></tr></thead>
                        <tbody>
                            @forelse($this->deposits() as $deposit)
                                <tr><td>{{ $this->money($deposit['amount']) }}</td><td>{{ $deposit['status'] }}</td><td>{{ $deposit['type'] ?: '-' }}</td><td>{{ $deposit['id_transaction'] ?: '-' }}</td><td>{{ $deposit['created_at'] }}</td></tr>
                            @empty
                                <tr><td colspan="5">Nenhum depósito encontrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="ud-note">Mostrando até 200 registros recentes.</p>
            </section>

            <section id="saques" class="ud-card ud-section">
                <h3>Histórico de saques</h3>
                <div class="ud-table-wrap">
                    <table class="ud-table">
                        <thead><tr><th>Valor</th><th>Status</th><th>Tipo</th><th>Data</th></tr></thead>
                        <tbody>
                            @forelse($this->withdrawals() as $withdrawal)
                                <tr><td>{{ $this->money($withdrawal['amount']) }}</td><td>{{ $withdrawal['status'] }}</td><td>{{ $withdrawal['type'] ?: '-' }}</td><td>{{ $withdrawal['created_at'] }}</td></tr>
                            @empty
                                <tr><td colspan="4">Nenhum saque encontrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="ud-note">Mostrando até 200 registros recentes.</p>
            </section>
        </div>

        <section id="indicacoes" class="ud-card ud-section">
            <h3>Afiliado / indicações</h3>
            <div class="ud-table-wrap">
                <table class="ud-table">
                    <thead><tr><th>Usuário</th><th>E-mail</th><th>Tipo</th><th>Comissão</th><th>Status</th><th>Data</th></tr></thead>
                    <tbody>
                        @forelse($this->indications() as $indication)
                            <tr><td>{{ $indication['name'] }}</td><td>{{ $indication['email'] }}</td><td>{{ $indication['commission_type'] ?: '-' }}</td><td>{{ $this->money($indication['commission_paid']) }}</td><td>{{ $indication['status'] ?: '-' }}</td><td>{{ $indication['created_at'] }}</td></tr>
                        @empty
                            <tr><td colspan="6">Nenhuma indicação encontrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="ud-note">Mostrando até 300 registros recentes.</p>
        </section>
    </div>
</x-filament::page>