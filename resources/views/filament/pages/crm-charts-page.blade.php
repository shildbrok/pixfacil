<x-filament::page>
    <style>
        .crm-wrap{display:grid;gap:18px}
        .crm-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .crm-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.20),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.14),transparent 32%)}
        .crm-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .crm-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .crm-grid{display:grid;gap:16px}
        @media(min-width:1150px){.crm-grid.two{grid-template-columns:1fr 1fr}}
        .crm-section{padding:16px 18px}
        .crm-section h3{margin:0 0 12px;color:#fff;font-size:16px;font-weight:900}
        .bar-list{display:grid;gap:11px}
        .bar-row{display:grid;grid-template-columns:82px 1fr 110px;gap:10px;align-items:center;color:#d4d4d4;font-size:12px}
        .bar-label strong{display:block;color:#e5e7eb}
        .bar-bg{height:12px;border-radius:999px;background:rgba(163, 163, 163,.18);overflow:hidden}
        .bar-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#fb923c,#22c55e)}
        .bar-fill.warn{background:linear-gradient(90deg,#f59e0b,#ef4444)}
        .bar-fill.purple{background:linear-gradient(90deg,#f97316,#fb923c)}
        .chart-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid rgba(163, 163, 163,.12);border-radius:14px;overflow:hidden}
        .chart-table th{background:rgba(20, 20, 20,.75);color:#a3a3a3;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;padding:10px}
        .chart-table td{border-top:1px solid rgba(163, 163, 163,.10);color:#ffedd5;font-size:12px;padding:10px;vertical-align:top}
    </style>

    @php($daily = $this->dailySeries())
    @php($providers = $this->providerSeries())
    @php($dist = $this->depositDistribution())
    @php($maxDaily = $this->maxDaily())
    @php($maxProviders = $this->maxProviders())
    @php($maxDist = $this->maxDistribution())

    <div class="crm-wrap">
        <section class="crm-card">
            <div class="crm-hero">
                <h2 class="crm-title">Gráficos e Métricas CRM</h2>
                <p class="crm-sub">Visualização operacional: fluxo financeiro, apostas, ganhos, lucro da casa, provedores reais e distribuição de usuários.</p>
            </div>
        </section>

        <div class="crm-grid two">
            <section class="crm-card crm-section">
                <h3>Depósitos pagos últimos 14 dias</h3>
                <div class="bar-list">
                    @foreach($daily as $row)
                        <div class="bar-row">
                            <div>{{ $row['date'] }}</div>
                            <div class="bar-bg"><div class="bar-fill" style="width: {{ $this->barWidth($row['deposits'], $maxDaily) }}%"></div></div>
                            <div>{{ $this->money($row['deposits']) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="crm-card crm-section">
                <h3>Lucro da casa últimos 14 dias</h3>
                <div class="bar-list">
                    @foreach($daily as $row)
                        <div class="bar-row">
                            <div>{{ $row['date'] }}</div>
                            <div class="bar-bg"><div class="bar-fill {{ $row['house'] < 0 ? 'warn' : '' }}" style="width: {{ $this->barWidth($row['house'], $maxDaily) }}%"></div></div>
                            <div>{{ $this->money($row['house']) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="crm-card crm-section">
            <h3>Apostas, ganhos e fluxo diário</h3>
            <table class="chart-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Depósitos</th>
                        <th>Saques</th>
                        <th>Apostas/perdas</th>
                        <th>Ganhos pagos</th>
                        <th>Lucro casa</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($daily as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $this->money($row['deposits']) }}</td>
                            <td>{{ $this->money($row['withdrawals']) }}</td>
                            <td>{{ $this->money($row['bets']) }}</td>
                            <td>{{ $this->money($row['wins']) }}</td>
                            <td>{{ $this->money($row['house']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <div class="crm-grid two">
            <section class="crm-card crm-section">
                <h3>Provedores reais por volume apostado</h3>
                <div class="bar-list">
                    @foreach($providers as $row)
                        <div class="bar-row" style="grid-template-columns:160px 1fr 110px">
                            <div class="bar-label"><strong>{{ $row['provider_code'] }}</strong><small>{{ $row['provider_name'] }}</small></div>
                            <div class="bar-bg"><div class="bar-fill purple" style="width: {{ $this->barWidth($row['total_bet'], $maxProviders) }}%"></div></div>
                            <div>{{ $this->money($row['total_bet']) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="crm-card crm-section">
                <h3>Distribuição por quantidade de depósitos</h3>
                <div class="bar-list">
                    @foreach($dist as $label => $count)
                        <div class="bar-row">
                            <div>{{ $label }}</div>
                            <div class="bar-bg"><div class="bar-fill" style="width: {{ $this->barWidth($count, $maxDist) }}%"></div></div>
                            <div>{{ $count }} usuários</div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="crm-card crm-section">
            <h3>Resumo por provedor real</h3>
            <table class="chart-table">
                <thead>
                    <tr>
                        <th>Agregador</th>
                        <th>Provedor real</th>
                        <th>Eventos</th>
                        <th>Apostado</th>
                        <th>Ganho</th>
                        <th>Lucro casa</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($providers as $row)
                        <tr>
                            <td>{{ $row['aggregator'] }}</td>
                            <td><strong>{{ $row['provider_code'] }}</strong><br><small>{{ $row['provider_name'] }}</small></td>
                            <td>{{ $row['events'] }}</td>
                            <td>{{ $this->money($row['total_bet']) }}</td>
                            <td>{{ $this->money($row['total_win']) }}</td>
                            <td>{{ $this->money($row['house_result']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>
</x-filament::page>
