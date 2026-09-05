<?php



namespace App\Filament\Pages;

use App\Models\ConfigRoundsFree;
use App\Models\Game;
use App\Models\Provider;
use App\Models\User;
use App\Services\PlayFiverService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class GiveRoundsFreePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.give-rounds-free-page';

    protected static ?string $title = 'Dar Rodadas Grátis';
    protected static ?string $navigationLabel = 'Dar Rodadas Grátis';
    protected static ?string $navigationGroup = 'Promoções e Benefícios';
    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'dar-rodadas-gratis';

    public ?array $data = [];

    /**
     * Provedores suportados para rodadas grátis (PlayFiver).
     * Resolvidos pelo NOME para não quebrar quando os provider_id do
     * catálogo mudam após uma reimportação de jogos.
     */
    protected array $allowedProviderNames = ['PRAGMATIC', 'PGSOFT', 'FATPANDA'];

    protected ?array $providerLabelsCache = null;

    protected function providerLabels(): array
    {
        if ($this->providerLabelsCache !== null) {
            return $this->providerLabelsCache;
        }

        return $this->providerLabelsCache = Provider::query()
            ->whereIn('name', $this->allowedProviderNames)
            ->pluck('name', 'id')
            ->all();
    }

    protected function allowedProviderIds(): array
    {
        return array_map('intval', array_keys($this->providerLabels()));
    }

    protected function allowedGamesOptions(): array
    {
        $providers = $this->providerLabels();

        return Game::query()
            ->where('status', 1)
            ->whereIn('provider_id', $this->allowedProviderIds())
            ->orderBy('provider_id')
            ->orderBy('game_name')
            ->get()
            ->mapWithKeys(fn ($game) => [
                $game->game_code => $game->game_name . ' [' . ($providers[$game->provider_id] ?? ('ID ' . $game->provider_id)) . ']',
            ])
            ->toArray();
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function mount(): void
    {
        $this->form->fill([
            'limit_profile' => 'standard',
            'rounds' => 50,
        ]);
    }

    public function form(Form $form): Form
    {
        $users = User::query()
            ->orderBy('email')
            ->pluck('email', 'email')
            ->toArray();

        return $form
            ->schema([
                Section::make('Resumo dos limites PlayFiver')
                    ->description('Use estes limites para evitar conflito com a conta comercial do cliente.')
                    ->schema([
                        Placeholder::make('playfiver_limits')
                            ->label('Limites conhecidos')
                            ->content(new HtmlString('
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                        <strong>Cliente padrão</strong><br>
                                        Recomendado: até <strong>50 rodadas</strong> para no máximo <strong>200 clientes</strong>.
                                    </div>
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                        <strong>Cliente com volume liberado</strong><br>
                                        PlayFiver pode aceitar até <strong>1000 rodadas</strong> e até <strong>10 mil clientes</strong>, conforme liberação comercial.
                                    </div>
                                </div>
                            ')),
                    ]),

                Section::make('Enviar Rodadas Grátis')
                    ->description('Agenda rodadas grátis diretamente na PlayFiver para um jogador específico.')
                    ->schema([
                        Select::make('email')
                            ->label('Jogador (e-mail)')
                            ->options($users)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->rules([
                                'required',
                                'email',
                                Rule::exists('users', 'email'),
                            ])
                            ->helperText('A PlayFiver usa este e-mail como user_code.'),

                        Select::make('game_code')
                            ->label('Jogo')
                            ->options($this->allowedGamesOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $config = ConfigRoundsFree::query()
                                    ->where('game_code', $state)
                                    ->where('status', 1)
                                    ->first();

                                if ($config) {
                                    $set('rounds', min(1000, max(1, (int) $config->spins)));
                                }
                            })
                            ->rules([
                                'required',
                                Rule::exists('games', 'game_code')->where(function ($query) {
                                    $query->where('status', 1)
                                        ->whereIn('provider_id', $this->allowedProviderIds());
                                }),
                            ]),

                        Select::make('limit_profile')
                            ->label('Perfil de limite')
                            ->options([
                                'standard' => 'Cliente padrão: até 50 rodadas / 200 clientes',
                                'volume' => 'Volume liberado: até 1000 rodadas / 10 mil clientes',
                            ])
                            ->default('standard')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                $rounds = (int) ($get('rounds') ?: 0);
                                if ($state === 'standard' && $rounds > 50) {
                                    $set('rounds', 50);
                                }
                            })
                            ->helperText('Este campo é uma orientação operacional. A validação final ainda é feita pela PlayFiver.'),

                        TextInput::make('rounds')
                            ->label('Quantidade de rodadas')
                            ->numeric()
                            ->inputMode('numeric')
                            ->required()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->rules(['required', 'integer', 'min:1', 'max:1000'])
                            ->helperText('Informe de 1 a 1000. Para cliente padrão, use até 50 rodadas.')
                            ->placeholder('Ex.: 50'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        try {
            if (config('app.demo')) {
                Notification::make()
                    ->title('Atenção')
                    ->body('Não é possível alterar em modo demonstração.')
                    ->danger()
                    ->send();

                return;
            }

            $this->validate([
                'data.email' => ['required', 'email', Rule::exists('users', 'email')],
                'data.game_code' => [
                    'required',
                    Rule::exists('games', 'game_code')->where(function ($query) {
                        $query->where('status', 1)
                            ->whereIn('provider_id', $this->allowedProviderIds());
                    }),
                ],
                'data.limit_profile' => ['required', Rule::in(['standard', 'volume'])],
                'data.rounds' => ['required', 'integer', 'min:1', 'max:1000'],
            ]);

            if (($this->data['limit_profile'] ?? 'standard') === 'standard' && (int) $this->data['rounds'] > 50) {
                Notification::make()
                    ->title('Limite padrão excedido')
                    ->body('Cliente padrão deve receber no máximo 50 rodadas. Use perfil de volume liberado somente se a PlayFiver autorizou.')
                    ->warning()
                    ->send();

                return;
            }

            $result = PlayFiverService::grantFreeBonus([
                'username' => $this->data['email'],
                'game_code' => $this->data['game_code'],
                'rounds' => (int) $this->data['rounds'],
            ]);

            if (! empty($result['status'])) {
                Notification::make()
                    ->title('Rodadas grátis agendadas')
                    ->body($result['message'] ?? 'O agendamento foi realizado com sucesso.')
                    ->success()
                    ->send();

                $this->data = ['limit_profile' => 'standard', 'rounds' => 50];
                $this->form->fill($this->data);

                return;
            }

            Notification::make()
                ->title('Falha ao agendar rodadas')
                ->body($result['message'] ?? 'Não foi possível concluir a operação.')
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro')
                ->body('Não foi possível completar a operação.')
                ->danger()
                ->send();
        }
    }
}