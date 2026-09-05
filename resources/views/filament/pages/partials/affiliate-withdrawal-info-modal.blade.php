<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-sm font-black text-gray-950 dark:text-white">
                    {{ $withdrawal->user?->name ?: ($withdrawal->name ?: 'Afiliado') }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $withdrawal->user?->email ?: '-' }}
                </div>
            </div>

            <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                {{ $statusLabel($withdrawal->status) }}
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Valor</div>
                <div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($withdrawal->amount) }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Tipo Pix</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $pixTypeLabel($withdrawal->pix_type) }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Solicitado</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $withdrawal->created_at?->format('d/m/Y H:i') ?: '-' }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Payment ID</div>
                <div class="mt-1 break-words text-sm font-black text-gray-950 dark:text-white">{{ $withdrawal->payment_id ?: '-' }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">CPF</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $withdrawal->cpf ?: ($withdrawal->user?->cpf ?: '-') }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Telefone</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $withdrawal->user?->phone ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Chave Pix</div>
        <div class="mt-2 break-words rounded-lg bg-gray-50 p-3 font-mono text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
            {{ $withdrawal->pix_key ?: '-' }}
        </div>
    </div>

    @if($withdrawal->bank_info)
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Dados bancários / bruto</div>
            <pre class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ is_array($withdrawal->bank_info) ? json_encode($withdrawal->bank_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $withdrawal->bank_info }}</pre>
        </div>
    @endif
</div>
