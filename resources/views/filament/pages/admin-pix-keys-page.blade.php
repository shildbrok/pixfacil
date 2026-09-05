<x-filament::page>
    <style>
        .pk-wrap{display:grid;gap:18px}
        .pk-card{border:1px solid rgba(163, 163, 163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20, 20, 20,.96),rgba(30, 30, 30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}
        .pk-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(249, 115, 22,.20),transparent 34%),radial-gradient(circle at top right,rgba(34,197,94,.14),transparent 32%)}
        .pk-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}
        .pk-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:1020px}
        .pk-note{margin-top:12px;border:1px solid rgba(251, 146, 60,.22);border-radius:16px;background:rgba(124, 45, 18,.20);padding:12px;color:#fed7aa;font-size:12px;line-height:1.55}
        .pk-stats{display:grid;gap:10px;padding:0 20px 18px}
        @media(min-width:900px){.pk-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1300px){.pk-stats{grid-template-columns:repeat(9,minmax(0,1fr))}}
        .pk-stat{border:1px solid rgba(163, 163, 163,.14);border-radius:15px;background:rgba(10, 10, 10,.28);padding:12px;min-height:78px}
        .pk-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .pk-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}
        .pk-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}
        .pk-table{padding:10px}
        .pk-modal-backdrop{position:fixed;inset:0;z-index:80;background:rgba(10, 10, 10,.78);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
        .pk-modal{width:min(880px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(163, 163, 163,.22);border-radius:22px;background:#141414;box-shadow:0 24px 80px rgba(0,0,0,.45)}
        .pk-modal-head{padding:18px 20px;border-bottom:1px solid rgba(163, 163, 163,.16);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .pk-modal-head h3{margin:0;color:#fff;font-size:17px;font-weight:950}
        .pk-modal-head p{margin:6px 0 0;color:#a3a3a3;font-size:12px;line-height:1.45}
        .pk-close{border:1px solid rgba(163, 163, 163,.22);border-radius:12px;background:rgba(20, 20, 20,.85);color:#fff;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer}
        .pk-modal-body{padding:18px 20px;display:grid;gap:12px}
        .pk-info-grid{display:grid;gap:10px}
        @media(min-width:800px){.pk-info-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .pk-info{border:1px solid rgba(163, 163, 163,.14);border-radius:14px;background:rgba(10, 10, 10,.30);padding:12px}
        .pk-info span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}
        .pk-info strong{display:block;margin-top:4px;color:#fff;font-size:13px;word-break:break-all}
    </style>

    @php($stats = $this->stats())

    <div class="pk-wrap">
        <section class="pk-card">
            <div class="pk-hero">
                <h2 class="pk-title">Chaves PIX dos Clientes</h2>
                <p class="pk-sub">
                    Gerencie as chaves PIX cadastradas pelos clientes. Esta tela não cria novas chaves; ela permite apenas revisar, editar, marcar principal e excluir.
                </p>

                <div class="pk-note">
                    Saques antigos continuam com os dados salvos no próprio histórico de saque. Excluir uma chave remove apenas o cadastro atual em <strong>user_pix_keys</strong>.
                </div>
            </div>

            <div class="pk-stats">
                <div class="pk-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Chaves</small></div>
                <div class="pk-stat"><span>Principais</span><strong>{{ $stats['default'] }}</strong><small>is_default</small></div>
                <div class="pk-stat"><span>CPF/CNPJ</span><strong>{{ $stats['document'] }}</strong><small>document</small></div>
                <div class="pk-stat"><span>E-mail</span><strong>{{ $stats['email'] }}</strong><small>email</small></div>
                <div class="pk-stat"><span>Telefone</span><strong>{{ $stats['phone'] }}</strong><small>phoneNumber</small></div>
                <div class="pk-stat"><span>Aleatória</span><strong>{{ $stats['random'] }}</strong><small>randomKey</small></div>
                <div class="pk-stat"><span>Clientes</span><strong>{{ $stats['users_with_keys'] }}</strong><small>com chave</small></div>
                <div class="pk-stat"><span>Hoje</span><strong>{{ $stats['updated_today'] }}</strong><small>atualizadas</small></div>
                <div class="pk-stat"><span>Última edição</span><strong style="font-size:12px">{{ $stats['last_update'] ? \Carbon\Carbon::parse($stats['last_update'])->format('d/m/Y H:i') : '-' }}</strong><small>user_pix_keys</small></div>
            </div>
        </section>

        <section class="pk-card pk-table">
            {{ $this->table }}
        </section>
    </div>

    @if($showDetailsModal && $selectedPixKey)
        <div class="pk-modal-backdrop" wire:click.self="closeDetails">
            <div class="pk-modal">
                <div class="pk-modal-head">
                    <div>
                        <h3>Chave PIX #{{ $selectedPixKey->id }}</h3>
                        <p>{{ $selectedPixKey->user?->name ?: 'Usuário removido' }} • {{ $selectedPixKey->user?->email ?: 'Sem e-mail' }}</p>
                    </div>

                    <button type="button" class="pk-close" wire:click="closeDetails">Fechar</button>
                </div>

                <div class="pk-modal-body">
                    <div class="pk-info-grid">
                        <div class="pk-info"><span>Cliente ID</span><strong>{{ $selectedPixKey->user_id ?: '—' }}</strong></div>
                        <div class="pk-info"><span>Cliente</span><strong>{{ $selectedPixKey->user?->name ?: '—' }}</strong></div>
                        <div class="pk-info"><span>E-mail</span><strong>{{ $selectedPixKey->user?->email ?: '—' }}</strong></div>
                        <div class="pk-info"><span>CPF do cliente</span><strong>{{ $this->formatCpf($selectedPixKey->user?->cpf) ?: '—' }}</strong></div>
                        <div class="pk-info"><span>Telefone</span><strong>{{ $selectedPixKey->user?->phone ?: '—' }}</strong></div>
                        <div class="pk-info"><span>Principal</span><strong>{{ $selectedPixKey->is_default ? 'Sim' : 'Não' }}</strong></div>
                        <div class="pk-info"><span>Titular</span><strong>{{ $selectedPixKey->holder_name ?: '—' }}</strong></div>
                        <div class="pk-info"><span>CPF titular</span><strong>{{ $this->formatCpf($selectedPixKey->holder_cpf) ?: '—' }}</strong></div>
                        <div class="pk-info"><span>Tipo</span><strong>{{ $this->typeLabel($selectedPixKey->key_type) }}</strong></div>
                        <div class="pk-info"><span>Chave PIX</span><strong>{{ $this->formatPixKey($selectedPixKey) ?: '—' }}</strong></div>
                        <div class="pk-info"><span>Saques normais</span><strong>{{ $this->withdrawalsCount($selectedPixKey) }}</strong></div>
                        <div class="pk-info"><span>Saques afiliado</span><strong>{{ $this->affiliateWithdrawalsCount($selectedPixKey) }}</strong></div>
                        <div class="pk-info"><span>Criada</span><strong>{{ $selectedPixKey->created_at?->format('d/m/Y H:i:s') ?: '—' }}</strong></div>
                        <div class="pk-info"><span>Atualizada</span><strong>{{ $selectedPixKey->updated_at?->format('d/m/Y H:i:s') ?: '—' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament::page>
