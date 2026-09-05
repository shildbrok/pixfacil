<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <div class="text-sm font-black text-gray-950 dark:text-white">{{ $wallet->name }}</div>
        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $wallet->wallet_key }}</div>

        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Saldo PlayFiver</div>
                <div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($wallet->balance) }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Tipo</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $typeLabel }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Alerta</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">
                    {{ $wallet->notify_enabled ? 'Ativo' : 'Inativo' }}
                </div>
                <div class="text-xs text-gray-500">{{ $wallet->notify_email ?: '-' }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Alertar abaixo de</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">
                    {{ $wallet->notify_threshold === null ? '-' : $money($wallet->notify_threshold) }}
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Sincronização</div>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                <strong>Última sync:</strong> {{ $wallet->last_synced_at?->format('d/m/Y H:i') ?: '-' }}
            </div>
            <div class="text-sm text-gray-700 dark:text-gray-300">
                <strong>Última notificação:</strong> {{ $wallet->last_notified_at?->format('d/m/Y H:i') ?: '-' }}
            </div>
        </div>

        @if($wallet->last_error)
            <div class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
                {{ $wallet->last_error }}
            </div>
        @endif
    </div>
</div>