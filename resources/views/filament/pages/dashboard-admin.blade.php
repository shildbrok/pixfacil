<x-filament::page>
    <style>
        .og-wrap{display:grid;gap:18px}
        .og-card{border:1px solid rgba(163, 163, 163,.18);border-radius:26px;background:linear-gradient(135deg,rgba(20, 20, 20,.97),rgba(30, 30, 30,.94));box-shadow:0 22px 56px rgba(0,0,0,.22);overflow:hidden}
        .og-hero{position:relative;padding:28px;background:
            radial-gradient(circle at top left,rgba(249, 115, 22,.22),transparent 34%),
            radial-gradient(circle at top right,rgba(249, 115, 22,.18),transparent 30%),
            radial-gradient(circle at bottom right,rgba(34,197,94,.12),transparent 32%);
        }
        .og-kicker{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(253, 186, 116,.28);border-radius:999px;background:rgba(249, 115, 22,.10);padding:7px 11px;color:#fed7aa;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.08em}
        .og-title{margin:16px 0 0;color:#fff;font-size:34px;font-weight:950;letter-spacing:-.05em;line-height:1.04}
        .og-sub{margin:10px 0 0;max-width:980px;color:#d4d4d4;font-size:14px;line-height:1.65}
        .og-grid{display:grid;gap:14px;padding:18px}
        @media(min-width:980px){.og-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.og-grid.two{grid-template-columns:1.2fr .8fr}}
        .og-info{border:1px solid rgba(163, 163, 163,.14);border-radius:20px;background:rgba(10, 10, 10,.30);padding:16px}
        .og-info span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .og-info strong{display:block;margin-top:6px;color:#fff;font-size:18px;font-weight:950}
        .og-info p{margin:7px 0 0;color:#d4d4d4;font-size:12px;line-height:1.55}
        .og-section{padding:20px 22px}
        .og-section h3{margin:0 0 10px;color:#fff;font-size:18px;font-weight:950;letter-spacing:-.02em}
        .og-section p{margin:0;color:#d4d4d4;font-size:14px;line-height:1.7}
        .og-links{display:grid;gap:10px;margin-top:12px}
        .og-link{display:flex;justify-content:space-between;gap:14px;align-items:center;border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(20, 20, 20,.45);padding:13px 14px;text-decoration:none;transition:.16s ease}
        .og-link:hover{transform:translateY(-1px);border-color:rgba(251, 146, 60,.38);background:rgba(20, 20, 20,.72)}
        .og-link strong{display:block;color:#fff;font-size:13px;font-weight:900}
        .og-link small{display:block;margin-top:3px;color:#a3a3a3;font-size:11px}
        .og-link em{font-style:normal;color:#fdba74;font-size:12px;font-weight:900;white-space:nowrap}
        .og-warning{border:1px solid rgba(251,191,36,.22);border-radius:20px;background:linear-gradient(135deg,rgba(120,53,15,.36),rgba(127,29,29,.18));padding:18px}
        .og-warning h3{color:#fde68a}
        .og-warning p{color:#fef3c7}
        .og-footer{padding:16px 22px;border-top:1px solid rgba(163, 163, 163,.12);color:#a3a3a3;font-size:12px}
    </style>

    <div class="og-wrap">
        <section class="og-card">
            <div class="og-hero">
                <div class="og-kicker">Desenvolvido por João (DEV)</div>

                <h1 class="og-title">Casino completo OndaGames</h1>

                <p class="og-sub">
                    Sistema desenvolvido por João (DEV) — Telegram @Joao_igaming.
                    Um projeto completo para operação de casino, com painel administrativo, integrações, métricas e estrutura operacional.
                </p>
            </div>

            <div class="og-grid three">
                <div class="og-info">
                    <span>Desenvolvedor</span>
                    <strong>João (DEV)</strong>
                    <p>Responsável pelo desenvolvimento do sistema. Contato: Telegram @Joao_igaming.</p>
                </div>

                <div class="og-info">
                    <span>Empresa</span>
                    <strong>OndaGames</strong>
                    <p>Mais informações podem ser encontradas no site oficial da empresa.</p>
                </div>

                <div class="og-info">
                    <span>Projeto</span>
                    <strong>Casino completo oficial</strong>
                    <p>Uso recomendado somente com licença, autorização e conformidade legal.</p>
                </div>
            </div>
        </section>

        <div class="og-grid two" style="padding:0">
            <section class="og-card og-section">
                <h3>Sobre este projeto</h3>
                <p>
                    Sistema desenvolvido por João (DEV).
                    O projeto foi criado para entregar uma estrutura completa de casino, com recursos administrativos, financeiros, jogos, afiliados, CRM, métricas e integrações.
                </p>

                <div class="og-links">
                    @foreach($this->projectLinks() as $link)
                        <a class="og-link" href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">
                            <div>
                                <strong>{{ $link['label'] }}</strong>
                                <small>{{ $link['description'] }}</small>
                            </div>
                            <em>Abrir</em>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="og-card og-section">
                <h3>Contato oficial</h3>
                <p>
                    Para informações, suporte, comunidade, atualizações ou materiais oficiais, utilize os canais informados nesta página.
                </p>

                <div class="og-info" style="margin-top:14px">
                    <span>Telegram</span>
                    <strong>https://t.me/Joao_igaming</strong>
                    <p>Contato direto com João (DEV).</p>
                </div>

                <div class="og-info" style="margin-top:10px">
                    <span>YouTube</span>
                    <strong>youtube.com/@centraligaming</strong>
                    <p>Central iGaming — conteúdos e vídeos.</p>
                </div>
            </section>
        </div>

        <section class="og-card og-section">
            <div class="og-warning">
                <h3>Nota legal e responsabilidade de uso</h3>
                <p>
                    Este casino não deve ser utilizado sem licenças, autorizações legais e conformidade com as leis aplicáveis.
                    O uso indevido pode acarretar consequências legais.
                </p>
                <p style="margin-top:10px">
                    A OndaGames e os responsáveis pelo projeto não se responsabilizam por qualquer uso indevido ou ilegal deste casino.
                    Consulte as leis locais e obtenha as licenças necessárias antes de utilizar este sistema.
                </p>
                <p style="margin-top:10px">
                    Pedimos que este casino seja utilizado de forma responsável, legal e dentro dos limites regulatórios aplicáveis.
                    Agradecemos a compreensão e colaboração de todos.
                </p>
            </div>
        </section>

        <section class="og-card og-footer">
            Sistema desenvolvido por João (DEV) • Telegram @Joao_igaming • Use de forma responsável e dentro da legislação aplicável.
        </section>
    </div>
</x-filament::page>
