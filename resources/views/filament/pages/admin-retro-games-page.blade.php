<x-filament::page>
    @php($stats = $this->getStats())
    <div class="grid gap-4 md:grid-cols-4 mb-5">
        @foreach([
            ['label' => 'Jogos importados', 'value' => $stats['total'], 'icon' => '🎮'],
            ['label' => 'Ativos', 'value' => $stats['active'], 'icon' => '🟢'],
            ['label' => 'Na Home', 'value' => $stats['home'], 'icon' => '🏠'],
            ['label' => 'Acessos', 'value' => number_format($stats['views'], 0, ',', '.'), 'icon' => '👁️'],
        ] as $card)
            <div class="rounded-2xl border border-white/10 bg-black/30 p-4">
                <div class="text-xs text-gray-400">{{ $card['icon'] }} {{ $card['label'] }}</div>
                <div class="mt-1 text-2xl font-bold text-white">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="mb-4 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm text-gray-300">
        <strong class="text-emerald-400">Motor separado do PlayFiver.</strong>
        Os jogos retrô usam tabelas próprias de catálogo e rodadas. Alterar esses parâmetros não muda providers, jogos ou sessões do cassino atual.
    </div>

    {{ $this->table }}
</x-filament::page>
