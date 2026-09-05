<x-filament::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Salvar configurações
            </x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6" heading="" icon="heroicon-o-information-circle">

    </x-filament::section>
</x-filament::page>