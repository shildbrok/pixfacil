<x-filament::page>
    @php($stats = $this->getStats())
    <div class="grid gap-4 md:grid-cols-4 mb-5">
        @foreach([
            ['label' => 'Em jogo', 'value' => $stats['open']],
            ['label' => 'Vitórias', 'value' => $stats['won']],
            ['label' => 'Perdas/expiradas', 'value' => $stats['lost']],
            ['label' => 'Total pago', 'value' => 'R$ '.number_format($stats['paid'], 2, ',', '.')],
        ] as $card)
            <div class="rounded-2xl border border-white/10 bg-black/30 p-4">
                <div class="text-xs text-gray-400">{{ $card['label'] }}</div>
                <div class="mt-1 text-2xl font-bold text-white">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>
    {{ $this->table }}
</x-filament::page>
