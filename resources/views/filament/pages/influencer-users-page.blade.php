<x-filament::page>
    <style>
        .inf-wrap{display:grid;gap:18px}
        .inf-card{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.90));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .inf-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.14),transparent 32%)}
        .inf-title{margin:0;color:#fff;font-size:24px;font-weight:900;letter-spacing:-.04em}
        .inf-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5}
        .inf-grid{display:grid;gap:10px;padding:0 22px 18px}
        @media(min-width:900px){.inf-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .inf-info{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.28);padding:13px}
        .inf-info strong{display:block;color:#fff;font-size:12px}
        .inf-info span{display:block;margin-top:5px;color:#a3a3a3;font-size:12px;line-height:1.45}
        .inf-table{padding:8px}
        .inf-table .fi-ta-header{padding:10px 12px}
        .inf-table .fi-ta-table{font-size:13px}
    </style>

    <div class="inf-wrap">
        <section class="inf-card">
            <div class="inf-hero">
                <h2 class="inf-title">Usuários Influenciadores</h2>
                <p class="inf-sub">
                    Modo demonstrativo para vídeos de publicidade. Depósitos e saques de usuários influenciadores não chamam gateway e não geram histórico financeiro.
                </p>
            </div>

            <div class="inf-grid">
                <div class="inf-info"><strong>Depósito demonstrativo</strong><span>Credita a carteira de depósito automaticamente, sem Transaction/Deposit.</span></div>
                <div class="inf-info"><strong>Saque demonstrativo</strong><span>Debita a carteira de saque e retorna sucesso, sem Withdrawal/gateway.</span></div>
                <div class="inf-info"><strong>Uso restrito</strong><span>Ative apenas para contas de publicidade e demonstração.</span></div>
            </div>
        </section>

        <section class="inf-card inf-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
