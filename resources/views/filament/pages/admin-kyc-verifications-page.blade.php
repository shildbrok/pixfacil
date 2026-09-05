<x-filament::page>
    <style>
        .kv-wrap{display:grid;gap:18px}
        .kv-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .kv-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.18),transparent 34%),radial-gradient(circle at top right,rgba(239,68,68,.13),transparent 32%)}
        .kv-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .kv-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}
        .kv-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .kv-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.kv-stats{grid-template-columns:repeat(5,minmax(0,1fr))}}
        .kv-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .kv-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .kv-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .kv-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .kv-table{padding:10px}
        .kv-modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(10, 10, 10,.80);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .kv-modal{width:min(1120px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(163, 163, 163,.20);border-radius:24px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45)}
        .kv-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .kv-modal-head h3{margin:0;color:#fff;font-size:18px;font-weight:950}
        .kv-modal-head p{margin:4px 0 0;color:#a3a3a3;font-size:12px}
        .kv-close{border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:rgba(20, 20, 20,.85);color:#fff;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer}
        .kv-modal-body{padding:14px;display:grid;gap:12px}
        .kv-info-grid{display:grid;gap:10px}
        @media(min-width:850px){.kv-info-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .kv-info{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.32);padding:12px}
        .kv-info span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .kv-info strong{display:block;margin-top:4px;color:#fff;font-size:13px;word-break:break-all}
        .kv-status-ok{color:#22c55e!important}.kv-status-bad{color:#ef4444!important}.kv-status-warn{color:#f59e0b!important}.kv-status-muted{color:#a3a3a3!important}
        .kv-docs{display:grid;gap:12px}
        @media(min-width:900px){.kv-docs{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .kv-doc{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.30);overflow:hidden}
        .kv-doc-head{padding:10px 12px;border-bottom:1px solid rgba(163, 163, 163,.12);color:#fff;font-size:12px;font-weight:900;display:flex;align-items:center;justify-content:space-between;gap:10px}
        .kv-doc-body{height:260px;background:#0a0a0a;display:flex;align-items:center;justify-content:center;padding:8px}
        .kv-doc-body img{width:100%;height:100%;object-fit:contain;border:0;border-radius:10px;background:#0a0a0a}
        .kv-doc-foot{padding:10px 12px}
        .kv-doc-foot a{color:#fdba74;font-size:12px;font-weight:800;text-decoration:none}
        .kv-actions{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.30);padding:14px;display:grid;gap:12px}
        .kv-actions h4{margin:0;color:#fff;font-size:14px;font-weight:950}
        .kv-actions p{margin:0;color:#a3a3a3;font-size:12px}
        .kv-input,.kv-textarea{width:100%;border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:#0a0a0a;color:#fff;padding:10px 12px;font-size:13px}
        .kv-textarea{min-height:82px;resize:vertical}
        .kv-buttons{display:grid;gap:10px}
        @media(min-width:760px){.kv-buttons{grid-template-columns:1fr 1fr}}
        .kv-btn{border:0;border-radius:12px;color:#fff;padding:11px 14px;font-size:13px;font-weight:900;cursor:pointer}
        .kv-btn-ok{background:#16a34a}
        .kv-btn-bad{background:#dc2626}
        .kv-finalized{border:1px solid rgba(163, 163, 163,.14);border-radius:16px;background:rgba(10, 10, 10,.30);padding:14px;color:#d4d4d4;font-size:13px}
    </style>

    @php($stats = $this->stats())

    <div class="kv-wrap">
        <section class="kv-card">
            <div class="kv-hero">
                <h2 class="kv-title">Verificações KYC</h2>
                <p class="kv-sub">
                    Clique em <strong>Verificar</strong> para abrir a análise completa, visualizar fotos/documentos e aprovar ou reprovar o cliente.
                </p>

                <div class="kv-note">
                    As imagens agora são exibidas diretamente na análise, sem baixar automaticamente.
                </div>
            </div>

            <div class="kv-stats">
                <div class="kv-stat"><span>Em análise</span><strong>{{ $stats['pending'] }}</strong><small>Aguardando decisão</small></div>
                <div class="kv-stat"><span>Aprovados</span><strong>{{ $stats['approved'] }}</strong><small>Histórico</small></div>
                <div class="kv-stat"><span>Rejeitados</span><strong>{{ $stats['rejected'] }}</strong><small>Histórico</small></div>
                <div class="kv-stat"><span>Hoje</span><strong>{{ $stats['today'] }}</strong><small>Envios do dia</small></div>
                <div class="kv-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Registros</small></div>
            </div>
        </section>

        <section class="kv-card kv-table">
            {{ $this->table }}
        </section>
    </div>

    @if($showVerifyModal && $selectedVerification)
        <div class="kv-modal-backdrop" wire:click.self="closeVerifyModal">
            <div class="kv-modal">
                <div class="kv-modal-head">
                    <div>
                        <h3>Verificar KYC #{{ $selectedVerification->id }}</h3>
                        <p>{{ $selectedVerification->user?->name ?? $selectedVerification->nome_completo }} • {{ $selectedVerification->user?->email ?? 'Sem e-mail' }}</p>
                    </div>
                    <button type="button" class="kv-close" wire:click="closeVerifyModal">Fechar</button>
                </div>

                <div class="kv-modal-body">
                    <div class="kv-info-grid">
                        <div class="kv-info"><span>Nome completo</span><strong>{{ $selectedVerification->nome_completo ?: '—' }}</strong></div>
                        <div class="kv-info"><span>CPF</span><strong>{{ $this->formatCpf($selectedVerification->cpf) }}</strong></div>
                        <div class="kv-info"><span>Status</span><strong class="{{ $this->statusClass($selectedVerification->status) }}">{{ $this->statusLabel($selectedVerification->status) }}</strong></div>
                        <div class="kv-info"><span>Enviado</span><strong>{{ $selectedVerification->created_at?->format('d/m/Y H:i') ?: '—' }}</strong></div>
                        <div class="kv-info"><span>Finalizado</span><strong>{{ $selectedVerification->aprovado_em?->format('d/m/Y H:i') ?: '—' }}</strong></div>
                        <div class="kv-info"><span>Telefone</span><strong>{{ $selectedVerification->user?->phone ?? $selectedVerification->user?->telefone ?? '—' }}</strong></div>
                        <div class="kv-info"><span>Usuário ID</span><strong>{{ $selectedVerification->user_id }}</strong></div>
                        <div class="kv-info"><span>Admin</span><strong>{{ $selectedVerification->aprovado_por ?: '—' }}</strong></div>
                    </div>

                    @if($selectedVerification->observacao_rejeicao)
                        <div class="kv-info">
                            <span>Motivo da rejeição</span>
                            <strong>{{ $selectedVerification->observacao_rejeicao }}</strong>
                        </div>
                    @endif

                    <div class="kv-docs">
                        @foreach(['selfie' => 'Selfie', 'frente' => 'Documento Frente', 'verso' => 'Documento Verso'] as $type => $label)
                            @php($url = $this->fileUrl($selectedVerification, $type))
                            <div class="kv-doc">
                                <div class="kv-doc-head">
                                    <span>{{ $label }}</span>
                                    @if($url)
                                        <a href="{{ $url }}" target="_blank" style="color:#fdba74;font-size:11px;text-decoration:none">Abrir</a>
                                    @endif
                                </div>
                                <div class="kv-doc-body">
                                    @if($url)
                                        <img src="{{ $url }}" alt="{{ $label }}">
                                    @else
                                        <span style="color:#737373;font-size:12px">Arquivo não enviado</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($selectedVerification->isPending())
                        <div class="kv-actions">
                            <div>
                                <h4>Decisão da análise</h4>
                                <p>Use o mesmo PIN administrativo para aprovar ou reprovar. Para reprovar, informe o motivo.</p>
                            </div>

                            <textarea class="kv-textarea" placeholder="Motivo da reprovação, obrigatório somente se reprovar" wire:model.defer="rejectReason"></textarea>

                            <input class="kv-input" type="password" inputmode="numeric" maxlength="6" placeholder="PIN administrativo" wire:model.defer="adminPin">

                            <div class="kv-buttons">
                                <button type="button" class="kv-btn kv-btn-ok" wire:click="approveSelectedVerification">
                                    Aprovar KYC
                                </button>

                                <button type="button" class="kv-btn kv-btn-bad" wire:click="rejectSelectedVerification">
                                    Reprovar KYC
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="kv-finalized">
                            Esta verificação já foi finalizada como <strong class="{{ $this->statusClass($selectedVerification->status) }}">{{ $this->statusLabel($selectedVerification->status) }}</strong>.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament::page>