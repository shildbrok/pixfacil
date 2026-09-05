<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Montante apostado</div>
            <div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($record->bet_volume ?? 0) }}</div>
        </div>

        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Montante de ganho</div>
            <div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($record->win_volume ?? 0) }}</div>
        </div>

        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Montante de perda</div>
            <div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($record->loss_volume ?? 0) }}</div>
        </div>

        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Montante depositado</div>
            <div class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $money($record->total_deposited ?? 0) }}</div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
        <div class="text-sm font-bold text-gray-950 dark:text-white">Frequência na casa</div>
        <div class="mt-2 flex flex-wrap gap-2 text-sm text-gray-600 dark:text-gray-300">
            <span class="rounded-full bg-primary-100 px-3 py-1 font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ $frequency }}</span>
            <span>Dias ativos nos últimos 30 dias: <strong>{{ (int) ($record->activity_days ?? 0) }}</strong></span>
            <span>Eventos recentes: <strong>{{ (int) ($record->activity_events ?? 0) }}</strong></span>
        </div>
    </div>

    <div class="text-xs text-gray-500 dark:text-gray-400">
        Critério de frequência: usa datas em <code>orders</code>, <code>transactions</code> e <code>daily_bonus_claims</code>. 
        A classificação é operacional e serve para análise interna.
    </div>
</div>
