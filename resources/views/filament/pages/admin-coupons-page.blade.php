<x-filament::page>
    <style>
        .cp-wrap{display:grid;gap:18px}
        .cp-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .cp-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.13),transparent 32%)}
        .cp-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .cp-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .cp-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .cp-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.cp-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.cp-stats{grid-template-columns:repeat(7,minmax(0,1fr))}}
        .cp-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .cp-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .cp-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .cp-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .cp-table{padding:10px}
    </style>

    @php($stats = $this->stats())

    <div class="cp-wrap">
        <section class="cp-card">
            <div class="cp-hero">
                <h2 class="cp-title">Cupons Promocionais</h2>
                <p class="cp-sub">
                    Crie, edite e acompanhe cupons de bônus usados no cadastro. Use o botão gerar para criar códigos únicos automaticamente.
                </p>

                <div class="cp-note">
                    O cupom adiciona bônus na carteira do usuário no cadastro e incrementa a contagem de usos.
                </div>
            </div>

            <div class="cp-stats">
                <div class="cp-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Cupons criados</small></div>
                <div class="cp-stat"><span>Ativos</span><strong>{{ $stats['active'] }}</strong><small>Válidos e com usos</small></div>
                <div class="cp-stat"><span>Expirados</span><strong>{{ $stats['expired'] }}</strong><small>Validade encerrada</small></div>
                <div class="cp-stat"><span>Esgotados</span><strong>{{ $stats['exhausted'] }}</strong><small>Limite atingido</small></div>
                <div class="cp-stat"><span>Usos</span><strong>{{ $stats['uses'] }}</strong><small>Resgates totais</small></div>
                <div class="cp-stat"><span>Bônus cadastrado</span><strong style="font-size:14px">{{ $this->money($stats['bonus']) }}</strong><small>Soma dos valores</small></div>
                <div class="cp-stat"><span>Próx. validade</span><strong style="font-size:12px">{{ $stats['next_expiration'] ? \Carbon\Carbon::parse($stats['next_expiration'])->format('d/m/Y') : '-' }}</strong><small>Cupom ativo</small></div>
            </div>
        </section>

        <section class="cp-card cp-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
