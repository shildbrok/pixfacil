<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs font-bold uppercase tracking-wide text-gray-500">Perfil</div><div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $profile }}</div></div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs font-bold uppercase tracking-wide text-gray-500">Total depositado</div><div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($record->total_deposited ?? 0) }}</div></div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs font-bold uppercase tracking-wide text-gray-500">Total apostado</div><div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($record->bet_volume ?? 0) }}</div></div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs font-bold uppercase tracking-wide text-gray-500">Total ganho</div><div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($record->win_volume ?? 0) }}</div></div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs font-bold uppercase tracking-wide text-gray-500">Total perda</div><div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($record->loss_volume ?? 0) }}</div></div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs font-bold uppercase tracking-wide text-gray-500">Resultado casa</div><div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($record->house_result ?? 0) }}</div></div>
    </div>
    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
        <div class="text-sm font-bold text-gray-950 dark:text-white">Contato</div>
        <div class="mt-2 grid gap-2 text-sm text-gray-600 dark:text-gray-300 sm:grid-cols-2">
            <div><strong>E-mail:</strong> {{ $record->email }}</div><div><strong>Telefone:</strong> {{ $record->phone ?: '-' }}</div>
            <div><strong>CPF:</strong> {{ $record->cpf ?: '-' }}</div><div><strong>Cadastro:</strong> {{ $record->created_at?->format('d/m/Y H:i') }}</div>
            <div><strong>Última atividade:</strong> {{ $record->last_activity_at ? \Carbon\Carbon::parse($record->last_activity_at)->format('d/m/Y H:i') : '-' }}</div>
            <div><strong>Dias ativos 30d:</strong> {{ (int) ($record->activity_days ?? 0) }}</div>
        </div>
    </div>
</div>
