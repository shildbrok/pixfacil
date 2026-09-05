<x-filament::page>
    {{-- Atualiza o status sozinho a cada 15s, sem recarregar a página. --}}
    <div wire:poll.15s style="display:flex;flex-direction:column;gap:1.25rem">

        {{-- ===== Status ao vivo ===== --}}
        <x-filament::section icon="heroicon-o-signal">
            <x-slot name="heading">Está rodando agora?</x-slot>
            <x-slot name="description">
                Atualiza sozinho a cada 15 segundos. O agendador carimba um horário toda vez
                que roda; o status abaixo vem da idade desse carimbo.
            </x-slot>

            @php
                $fg = match ($scheduler['color']) {
                    'success' => 'rgb(34,197,94)',
                    'warning' => 'rgb(202,138,4)',
                    default   => 'rgb(239,68,68)',
                };
                $bg = match ($scheduler['color']) {
                    'success' => 'rgba(34,197,94,.12)',
                    'warning' => 'rgba(234,179,8,.14)',
                    default   => 'rgba(239,68,68,.12)',
                };
            @endphp

            <div style="border-radius:.75rem;padding:1.15rem 1.25rem;background:{{ $bg }};border:1px solid {{ $fg }}33;max-width:420px">
                <div style="font-size:.8rem;font-weight:600;color:var(--gray-500)">Agendador (cron)</div>
                <div style="display:flex;align-items:center;gap:.55rem;margin-top:.4rem">
                    <span style="width:.7rem;height:.7rem;border-radius:999px;background:{{ $fg }};box-shadow:0 0 0 4px {{ $fg }}22"></span>
                    <span style="font-size:1.3rem;font-weight:700;color:{{ $fg }}">{{ $scheduler['label'] }}</span>
                </div>
                <div style="font-size:.8rem;color:var(--gray-500);margin-top:.5rem">
                    @if ($scheduler['last'])
                        Última execução: {{ $scheduler['last']->diffForHumans() }}
                    @else
                        Nunca rodou — falta registrar o cron abaixo.
                    @endif
                </div>
                <div style="font-size:.74rem;color:var(--gray-400);margin-top:.15rem">
                    Roda todas as tarefas: sessões de jogo, saldo do agregador e o sistema de distribuição.
                </div>
            </div>

            @if ($scheduler['state'] === 'ok')
                <div style="margin-top:1rem;font-size:.85rem;color:rgb(34,197,94);font-weight:600">
                    ✓ Tudo certo. Nenhum worker é necessário — o agendador faz tudo.
                </div>
            @else
                <div style="margin-top:1rem;border-radius:.6rem;padding:.8rem 1rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);font-size:.84rem;color:rgb(220,38,38)">
                    <strong>O agendador não está rodando.</strong>
                    Registre o cron job abaixo no seu painel de hospedagem (CloudPanel → Cron Jobs).
                    Enquanto isso, sessões de jogo não fecham, o saldo do agregador não sincroniza e o
                    sistema de distribuição fica parado.
                </div>
            @endif
        </x-filament::section>

        {{-- ===== O que registrar no CloudPanel ===== --}}
        <x-filament::section icon="heroicon-o-clipboard-document-list">
            <x-slot name="heading">Como registrar no CloudPanel</x-slot>
            <x-slot name="description">
                É <strong>um cron job só</strong>. Em <em>Sites → seu site → Cron Jobs → Novo Cron Job</em>,
                preencha assim:
            </x-slot>

            {{-- Campos de horário do CloudPanel --}}
            <div style="font-size:.82rem;font-weight:600;margin-bottom:.5rem">1. Horário — deixe tudo a cada minuto</div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.35rem">
                <div style="border:1px solid var(--gray-200);border-radius:.5rem;padding:.4rem .7rem;background:var(--gray-50)">
                    <span style="font-size:.7rem;color:var(--gray-500)">Modelo</span>
                    <div style="font-weight:700;font-size:.85rem">Todo minuto</div>
                </div>
                @foreach (['Minuto','Hora','Dia','Mês','Dia da semana'] as $campo)
                    <div style="border:1px solid var(--gray-200);border-radius:.5rem;padding:.4rem .7rem;background:var(--gray-50);text-align:center;min-width:74px">
                        <span style="font-size:.7rem;color:var(--gray-500)">{{ $campo }}</span>
                        <div style="font-weight:700;font-size:1rem;font-family:monospace">*</div>
                    </div>
                @endforeach
            </div>
            <div style="font-size:.74rem;color:var(--gray-400);margin-bottom:1rem">
                Selecionar o modelo “Todo minuto” já preenche os cinco campos com <code>*</code>.
            </div>

            {{-- Campo Comando --}}
            <div style="font-size:.82rem;font-weight:600;margin-bottom:.35rem">2. Comando — cole exatamente isto</div>
            <div x-data="{ copied: false, cmd: @js($schedulerCommand) }" style="position:relative">
                <pre style="overflow-x:auto;background:var(--gray-950,#0a0a0a);color:#e5e5e5;border-radius:.6rem;padding:.85rem 1rem;font-size:.78rem;line-height:1.5;margin:0;white-space:pre-wrap;word-break:break-all">{{ $schedulerCommand }}</pre>
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText(cmd); copied = true; setTimeout(() => copied = false, 1500)"
                    style="position:absolute;top:.5rem;right:.5rem;font-size:.72rem;padding:.25rem .6rem;border-radius:.4rem;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;cursor:pointer"
                >
                    <span x-show="!copied">Copiar</span>
                    <span x-show="copied" x-cloak>Copiado!</span>
                </button>
            </div>

            <div style="margin-top:.9rem;font-size:.78rem;color:var(--gray-500)">
                Depois é só <strong>Adicionar Cron Job</strong>. Em até 1 minuto o status lá em cima fica verde.
                O PHP usado é <code>{{ $phpBinary }}</code> — se o seu servidor usar outra versão, ajuste o número.
            </div>
        </x-filament::section>

        {{-- ===== Canário da fila (normalmente 0) ===== --}}
        @if ($queue['pending'] > 0 || $queue['failed'] > 0)
            <x-filament::section icon="heroicon-o-exclamation-triangle">
                <x-slot name="heading">Atenção na fila</x-slot>
                <div style="font-size:.85rem;color:var(--gray-600)">
                    Normalmente esta seção nem aparece — nada é enfileirado nesta configuração.
                    @if ($queue['pending'] > 0)
                        Há <strong>{{ $queue['pending'] }}</strong> job(s) parado(s) na fila
                        @if ($queue['oldest_age'] !== null)
                            (o mais antigo há {{ \Carbon\CarbonInterval::seconds($queue['oldest_age'])->cascade()->forHumans(short: true) }})
                        @endif
                        — sinal de que algum código voltou a usar a fila sem worker.
                    @endif
                    @if ($queue['failed'] > 0)
                        <strong>{{ $queue['failed'] }}</strong> job(s) falharam.
                    @endif
                </div>
            </x-filament::section>
        @endif

        <div>
            <x-filament::button wire:click="refreshStatus" icon="heroicon-o-arrow-path" color="gray">
                Atualizar agora
            </x-filament::button>
        </div>
    </div>
</x-filament::page>
