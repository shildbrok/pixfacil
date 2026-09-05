<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <div style="width:46px;height:46px;border-radius:14px;border:1px solid rgba(163, 163, 163,.45);background:{{ $token->color_value }}"></div>
            <div>
                <div class="text-sm font-black text-gray-950 dark:text-white">{{ $token->label }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $token->token }}</div>
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Valor</div>
                <div class="mt-1 break-words font-mono text-sm font-black text-gray-950 dark:text-white">{{ $token->color_value }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Variável CSS</div>
                <div class="mt-1 break-words font-mono text-sm font-black text-gray-950 dark:text-white">{{ $token->css_variable }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Família</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $token->family ?: '-' }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Grupo</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $token->group_name ?: '-' }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Uso</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $token->total_occurrences }} ocorrência(s)</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Status</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">
                    {{ $token->is_active ? 'Ativa' : 'Inativa' }} / {{ $token->is_editable ? 'Editável' : 'Bloqueada' }}
                </div>
            </div>
        </div>
    </div>

    @if($token->notes)
        <div class="rounded-xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">
            {{ $token->notes }}
        </div>
    @endif
</div>