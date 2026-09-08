<?php

namespace App\Filament\Pages;

use App\Models\Gateway;
use App\Models\Setting;
use App\Support\AdminActionGuard;
use App\Support\AdminAudit;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class AdminPaymentGatewaysPage extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string $view = 'filament.pages.admin-payment-gateways-page';
    protected static ?string $title = 'Gateways de Pagamento';
    protected static ?string $navigationLabel = 'Gateways de Pagamento';
    protected static ?string $navigationGroup = 'Configurações da Plataforma';
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'gateways-de-pagamento';

    public ?array $data = [];
    public ?Gateway $setting = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canView(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->setting = Gateway::query()->first();
        $settings = Setting::query()->first();

        $data = [
            'gerapix_uri' => $this->setting?->gerapix_uri,
            'digitopay_uri' => $this->setting?->digitopay_uri,
            'pixup_uri' => $this->setting?->pixup_uri,
            'podpay_uri' => $this->setting?->podpay_uri,
            'abilitypay_uri' => $this->setting?->abilitypay_uri,
            'forceonepay_uri' => $this->setting?->forceonepay_uri,
            'deposit_gateway' => $settings?->deposit_gateway ?: \App\Services\Gateways\GatewayManager::FALLBACK,
            'saque' => $settings?->saque ?: \App\Services\Gateways\GatewayManager::FALLBACK,
            'digitopay_is_enable' => (bool) ($settings?->digitopay_is_enable ?? false),
            'pixup_is_enable' => (bool) ($settings?->pixup_is_enable ?? false),
            'podpay_is_enable' => (bool) ($settings?->podpay_is_enable ?? false),
            'abilitypay_is_enable' => (bool) ($settings?->abilitypay_is_enable ?? false),
            'forceonepay_is_enable' => (bool) ($settings?->forceonepay_is_enable ?? false),
        ];

        foreach ($this->secretFields() as $field) {
            $data[$field] = null;
        }

        $this->form->fill($data);
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function saveGatewaysAction(): Action
    {
        return Action::make('saveGateways')
            ->label('Salvar gateways')
            ->icon('heroicon-o-check')
            ->color('primary')
            ->modalHeading('Confirmar alteração dos gateways')
            ->modalDescription('Informe o PIN administrativo. Segredos deixados em branco serão mantidos sem alteração.')
            ->modalSubmitActionLabel('Confirmar e salvar')
            ->form([
                TextInput::make('admin_password')->label('PIN administrativo')->password()->numeric()->length(6)->required(),
            ])
            ->action(fn (array $data) => $this->submit($data));
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->statePath('data')->schema([
            $this->activeGatewaySection(),
            Tabs::make('Gateways')->persistTabInQueryString()->tabs([
                $this->gatewayTab('DigitoPay', 'digitopay', [
                    ['digitopay_client_id', 'Client ID'],
                    ['digitopay_secret', 'Secret'],
                ], true),
                $this->gatewayTab('AbilityPay', 'abilitypay', [
                    ['abilitypay_client_id', 'X-Client-Id'],
                    ['abilitypay_client_secret', 'X-Client-Secret'],
                ], true, url('/api/abilitypay/callback')),
                $this->gatewayTab('PIXUP', 'pixup', [
                    ['pixup_client_id', 'Client ID'],
                    ['pixup_client_secret', 'Client Secret'],
                    ['pixup_webhook_secret', 'Webhook Secret (opcional)'],
                ], true),
                $this->gatewayTab('PodPay', 'podpay', [
                    ['podpay_api_key', 'API Key (x-api-key)'],
                ], true),
                $this->gatewayTab('GeraPix', 'gerapix', [
                    ['gerapix_secret_token', 'Token secreto'],
                ], false),
                $this->gatewayTab('ForceOnePay', 'forceonepay', [
                    ['forceonepay_token', 'Token contratado'],
                ], true),
            ]),
        ]);
    }

    private function activeGatewaySection(): Section
    {
        $options = \App\Services\Gateways\GatewayManager::options();

        return Section::make('Gateway ativo')
            ->description('Escolha qual gateway processa depósitos e saques. A alteração exige PIN ao salvar.')
            ->schema([
                Forms\Components\Select::make('deposit_gateway')->label('Gateway de depósito')->options($options)->required()->native(false),
                Forms\Components\Select::make('saque')->label('Gateway de saque')->options($options)->required()->native(false),
            ])->columns(2);
    }

    private function gatewayTab(string $label, string $prefix, array $secrets, bool $hasEnable, ?string $callback = null): Tabs\Tab
    {
        $configured = collect($secrets)->every(fn (array $pair) => filled($this->setting?->{$pair[0]}));
        $schema = [];

        $schema[] = Section::make($label . ' — status')->schema([
            Forms\Components\Placeholder::make($prefix . '_configured')
                ->label('Credenciais')
                ->content(new HtmlString($configured
                    ? '<span style="color:#22c55e;font-weight:800">● Configuradas</span>'
                    : '<span style="color:#f59e0b;font-weight:800">● Incompletas</span>')),
            Forms\Components\Placeholder::make($prefix . '_security')
                ->label('Segurança')
                ->content('Os segredos não são exibidos. Preencha somente o que quiser substituir.'),
        ])->columns(2);

        $fields = [];
        if ($hasEnable) {
            $fields[] = Forms\Components\Toggle::make($prefix . '_is_enable')->label($label . ' ativo')->columnSpanFull();
        }
        $fields[] = TextInput::make($prefix . '_uri')->label('API Base URL')->maxLength(191)->columnSpanFull();
        foreach ($secrets as [$name, $fieldLabel]) {
            $fields[] = $this->secretField($name, $fieldLabel, $this->setting?->{$name});
        }
        $schema[] = Section::make('Configuração')->schema($fields)->columns(2);

        if ($callback) {
            $schema[] = Section::make('URL de callback')->schema([
                Forms\Components\Placeholder::make($prefix . '_callback')->label('Callback')->content($callback),
            ]);
        }

        return Tabs\Tab::make($label)->schema($schema);
    }

    private function secretField(string $name, string $label, ?string $configuredValue): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->password()
            ->revealable()
            ->default(null)
            ->placeholder(filled($configuredValue) ? 'Configurado — deixe vazio para manter' : 'Digite o valor')
            ->maxLength(191)
            ->columnSpanFull()
            ->helperText(filled($configuredValue)
                ? 'Existe um valor salvo e criptografado. Este campo começa vazio por segurança.'
                : 'Nenhum valor configurado.')
            ->dehydrated(fn ($state) => filled($state));
    }

    public function submit(array $confirmation = []): void
    {
        try {
            if (config('app.demo')) {
                Notification::make()->title('Atenção')->body('Alterações desativadas na versão demo.')->danger()->send();
                return;
            }

            $guard = app(AdminActionGuard::class);
            if (! $guard->confirm((string) ($confirmation['admin_password'] ?? ''))) {
                $wait = $guard->availableIn();
                Notification::make()->title('Acesso negado')->body($wait > 0 ? 'Muitas tentativas. Aguarde ' . $wait . ' segundo(s).' : 'PIN administrativo incorreto.')->danger()->send();
                return;
            }

            $state = $this->form->getState();
            $settingKeys = ['deposit_gateway','saque','digitopay_is_enable','pixup_is_enable','podpay_is_enable','abilitypay_is_enable','forceonepay_is_enable'];
            $settingsPayload = array_intersect_key($state, array_flip($settingKeys));
            $gatewayPayload = array_diff_key($state, array_flip($settingKeys));

            foreach ($this->secretFields() as $secret) {
                if (blank($gatewayPayload[$secret] ?? null)) unset($gatewayPayload[$secret]);
            }

            foreach (['digitopay_is_enable','pixup_is_enable','podpay_is_enable','abilitypay_is_enable','forceonepay_is_enable'] as $flag) {
                if (array_key_exists($flag, $settingsPayload)) $settingsPayload[$flag] = ! empty($settingsPayload[$flag]) ? 1 : 0;
            }

            $settings = Setting::query()->firstOrCreate(['id' => 1], ['software_name' => config('app.name', 'Plataforma')]);
            $beforeSettings = $settings->only($settingKeys);
            $settings->update($settingsPayload);

            $gateway = Gateway::query()->first();
            if ($gateway) $gateway->update($gatewayPayload); else $gateway = Gateway::query()->create($gatewayPayload);

            AdminAudit::log(
                'gateways.update',
                $gateway,
                $beforeSettings,
                array_merge($settings->fresh()->only($settingKeys), ['secrets_changed' => array_values(array_intersect(array_keys($gatewayPayload), $this->secretFields()))]),
                'Atualizou gateways sem expor segredos'
            );

            Notification::make()->title('Gateways atualizados')->body('Credenciais salvas. Campos secretos vazios foram preservados.')->success()->send();
            $this->mount();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Erro ao salvar gateways')->body('Não foi possível salvar as configurações. Consulte os logs do sistema.')->danger()->send();
        }
    }

    public function stats(): array
    {
        $gateway = $this->setting ?? Gateway::query()->first();
        $status = $this->gatewayStatus($gateway);
        return [
            'configured' => collect($status)->where('configured', true)->count(),
            'total' => count($status),
            'last_update' => $gateway?->updated_at,
        ];
    }

    public function gatewayStatus(?Gateway $gateway = null): array
    {
        $gateway ??= $this->setting ?? Gateway::query()->first();
        return [
            'DigitoPay' => ['configured' => filled($gateway?->digitopay_client_id) && filled($gateway?->digitopay_secret), 'base_url' => $gateway?->digitopay_uri ?: '-'],
            'AbilityPay' => ['configured' => filled($gateway?->abilitypay_client_id) && filled($gateway?->abilitypay_client_secret), 'base_url' => $gateway?->abilitypay_uri ?: '-'],
            'PIXUP' => ['configured' => filled($gateway?->pixup_client_id) && filled($gateway?->pixup_client_secret), 'base_url' => $gateway?->pixup_uri ?: '-'],
            'PodPay' => ['configured' => filled($gateway?->podpay_api_key), 'base_url' => $gateway?->podpay_uri ?: '-'],
            'GeraPix' => ['configured' => filled($gateway?->gerapix_secret_token), 'base_url' => $gateway?->gerapix_uri ?: '-'],
            'ForceOnePay' => ['configured' => filled($gateway?->forceonepay_token), 'base_url' => $gateway?->forceonepay_uri ?: '-'],
        ];
    }

    private function secretFields(): array
    {
        return [
            'gerapix_secret_token',
            'digitopay_client_id',
            'digitopay_secret',
            'pixup_client_id',
            'pixup_client_secret',
            'pixup_webhook_secret',
            'podpay_api_key',
            'abilitypay_client_id',
            'abilitypay_client_secret',
            'forceonepay_token',
        ];
    }

    public function webhookUrl(string $path): string
    {
        return rtrim((string) config('app.url', url('/')), '/') . '/' . ltrim($path, '/');
    }
}
