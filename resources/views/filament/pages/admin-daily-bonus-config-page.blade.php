<x-filament::page>
    <style>
        .bonus-wrap{display:grid;gap:18px}
        .bonus-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .bonus-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.14),transparent 32%)}
        .bonus-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .bonus-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:920px}
        .bonus-badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:13px}
        .bonus-badge{border:1px solid rgba(163, 163, 163,.2);border-radius:999px;background:rgba(20, 20, 20,.52);padding:7px 10px;color:#e5e7eb;font-size:12px;font-weight:800}
        .bonus-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.bonus-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.bonus-stats{grid-template-columns:repeat(7,minmax(0,1fr))}}
        .bonus-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .bonus-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .bonus-stat strong{display:block;margin-top:4px;color:#fff;font-size:17px;font-weight:950}
        .bonus-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .bonus-grid{display:grid;gap:16px}
        @media(min-width:1100px){.bonus-grid.two{grid-template-columns:1fr 1fr}}
        .bonus-section{padding:18px 20px}
        .bonus-section h3{margin:0 0 12px;color:#fff;font-size:16px;font-weight:900}
        .bonus-note{border:1px solid rgba(251,191,36,.2);border-radius:16px;background:rgba(120,53,15,.20);padding:13px;color:#fde68a;font-size:12px;line-height:1.55}
        .bonus-form form{display:grid;gap:14px}
        .bonus-list{display:grid;gap:10px}
        .bonus-row{border:1px solid rgba(163, 163, 163,.12);border-radius:14px;background:rgba(20, 20, 20,.42);padding:12px}
        .bonus-row strong{display:block;color:#fff;font-size:13px}
        .bonus-row small{display:block;margin-top:4px;color:#a3a3a3;font-size:12px;line-height:1.45}
    </style>

    @php($stats = $this->stats())

    <div class="bonus-wrap">
        <section class="bonus-card">
            <div class="bonus-hero">
                <h2 class="bonus-title">Bônus Diário</h2>
                <p class="bonus-sub">
                    Página própria para configurar o valor do bônus diário e o intervalo de resgate, sem Resource antigo.
                </p>

                <div class="bonus-badges">
                    <span class="bonus-badge">Valor atual: {{ $this->money($stats['bonus_value']) }}</span>
                    <span class="bonus-badge">Intervalo: {{ $stats['cycle_hours'] }}h</span>
                    <span class="bonus-badge">Risco: {{ $stats['risk_label'] }}</span>
                </div>
            </div>

            <div class="bonus-stats">
                <div class="bonus-stat"><span>Valor do bônus</span><strong>{{ $this->money($stats['bonus_value']) }}</strong><small>Valor por resgate</small></div>
                <div class="bonus-stat"><span>Intervalo</span><strong>{{ $stats['cycle_hours'] }}h</strong><small>Tempo entre resgates</small></div>
                <div class="bonus-stat"><span>Resgates hoje</span><strong>{{ $stats['claims_today'] }}</strong><small>Custo: {{ $this->money($stats['cost_today']) }}</small></div>
                <div class="bonus-stat"><span>Resgates ontem</span><strong>{{ $stats['claims_yesterday'] }}</strong><small>Custo: {{ $this->money($stats['cost_yesterday']) }}</small></div>
                <div class="bonus-stat"><span>Total resgates</span><strong>{{ $stats['total_claims'] }}</strong><small>Histórico geral</small></div>
                <div class="bonus-stat"><span>Custo estimado</span><strong style="font-size:13px">{{ $this->money($stats['cost_total_estimated']) }}</strong><small>Total histórico estimado</small></div>
                <div class="bonus-stat"><span>Último resgate</span><strong style="font-size:12px">{{ $stats['last_claim'] ? \Carbon\Carbon::parse($stats['last_claim'])->format('d/m/Y H:i') : '-' }}</strong><small>daily_bonus_claims</small></div>
            </div>
        </section>

        <div class="bonus-grid two">
            <section class="bonus-card bonus-section bonus-form">
                <h3>Configuração</h3>

                <form wire:submit="save">
                    {{ $this->form }}

                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Salvar configuração
                    </x-filament::button>
                </form>
            </section>

            <section class="bonus-card bonus-section">
                <h3>Boas práticas</h3>

                <div class="bonus-list">
                    <div class="bonus-row">
                        <strong>Intervalo recomendado</strong>
                        <small>24 horas é o padrão mais seguro para evitar abuso e multi-contas.</small>
                    </div>

                    <div class="bonus-row">
                        <strong>Valor do bônus</strong>
                        <small>Valores altos aumentam o custo operacional e podem impactar o saldo dos jogadores.</small>
                    </div>

                    <div class="bonus-row">
                        <strong>Histórico</strong>
                        <small>O histórico de resgates continua na página “Histórico Bônus Diário”.</small>
                    </div>

                    <div class="bonus-note">
                        Esta página altera apenas a configuração atual do bônus diário. O pagamento, validação de resgate e regras extras continuam no fluxo do backend.
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-filament::page>
