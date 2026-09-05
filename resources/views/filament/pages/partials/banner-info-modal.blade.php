<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        @if($imageUrl($banner->image))
            <img src="{{ $imageUrl($banner->image) }}" alt="Banner" class="w-full rounded-xl object-cover" style="max-height: 360px;">
        @else
            <div class="rounded-xl bg-gray-100 p-8 text-center text-sm text-gray-500 dark:bg-gray-800">
                Imagem não encontrada.
            </div>
        @endif

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Posição</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $typeLabel($banner->type) }}</div>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Atualizado</div>
                <div class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $banner->updated_at?->format('d/m/Y H:i') ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Link</div>
        <div class="mt-2 break-words rounded-lg bg-gray-50 p-3 font-mono text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
            {{ $banner->link ?: '-' }}
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Descrição</div>
        <div class="mt-2 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">
            {{ $banner->description ?: '-' }}
        </div>
    </div>
</div>