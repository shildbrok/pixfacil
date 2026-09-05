<x-filament::page>
    <style>
        .give-wrap{display:grid;gap:18px}.give-hero{border:1px solid rgba(163, 163, 163,.22);border-radius:24px;background:radial-gradient(circle at top left,rgba(34,197,94,.22),transparent 34%),radial-gradient(circle at top right,rgba(249, 115, 22,.18),transparent 32%),linear-gradient(135deg,rgba(20, 20, 20,.98),rgba(10, 10, 10,.98));padding:24px;box-shadow:0 18px 55px rgba(0,0,0,.20)}.give-title{color:white;font-size:28px;font-weight:900;letter-spacing:-.04em}.give-sub{margin-top:8px;color:#d4d4d4;font-size:14px;line-height:1.55}.give-form{border:1px solid rgba(163, 163, 163,.18);border-radius:22px;background:rgba(255,255,255,.96);padding:18px;box-shadow:0 12px 34px rgba(20, 20, 20,.08)}.dark .give-form{background:rgba(23, 23, 23,.96)}.give-actions{display:flex;justify-content:flex-end;padding-top:6px}
    </style>

    <div class="give-wrap">
        <div class="give-hero">
            <div class="give-title">Dar Rodadas Grátis</div>
            <div class="give-sub">Envie rodadas para um jogador específico. Para contas padrão, use até 50 rodadas; para contas com volume liberado, a PlayFiver pode aceitar até 1000.</div>
        </div>

        <form wire:submit="submit" class="give-form space-y-6">
            {{ $this->form }}

            <div class="give-actions">
                <x-filament::button type="submit" icon="heroicon-o-paper-airplane" size="lg">
                    Dar rodadas grátis
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament::page>