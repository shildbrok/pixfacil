<?php



namespace App\Filament\Pages;

use App\Models\Gateway;
use App\Support\AdminActionGuard;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function mount(): void
    {
        $this->setting = Gateway::query()->first();

        $data = $this->setting?->toArray() ?? [];

        // Seletor de gateway ativo e flags vêm de `settings`; credenciais de
        // `gateways`. O form junta os dois e o submit separa de novo.
        $s = \App\Models\Setting::query()->first();
        $data['deposit_gateway']     = $s->deposit_gateway ?: \App\Services\Gateways\GatewayManager::FALLBACK;
        $data['saque']               = $s->saque ?: \App\Services\Gateways\GatewayManager::FALLBACK;
        $data['digitopay_is_enable'] = (bool) ($s->digitopay_is_enable ?? false);
        $data['pixup_is_enable']     = (bool) ($s->pixup_is_enable ?? false);
        $data['podpay_is_enable']    = (bool) ($s->podpay_is_enable ?? false);
        $data['abilitypay_is_enable'] = (bool) ($s->abilitypay_is_enable ?? false);
        $data['forceonepay_is_enable'] = (bool) ($s->forceonepay_is_enable ?? false);

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
            ->modalDescription('Informe o PIN administrativo para salvar as alterações.')
            ->modalSubmitActionLabel('Confirmar e salvar')
            ->form([
                TextInput::make('admin_password')
                    ->label('PIN administrativo')
                    ->placeholder('Digite o PIN de 6 dígitos')
                    ->password()
                    ->numeric()
                    ->length(6)
                    ->required(),
            ])
            ->action(fn (array $data) => $this->submit($data));
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->statePath('data')
            ->schema([
                $this->activeGatewaySection(),

                // Na ordem da recomendação (#1 primeiro), não na ordem em que
                // foram integrados: o cliente que abre esta página está
                // escolhendo um gateway, e a primeira aba é a que ele deve ver.
                Tabs::make('Gateways')
                    ->persistTabInQueryString()
                    ->tabs([
                        $this->digitoPayTab(),
                        $this->abilityPayTab(),
                        $this->pixupTab(),
                        $this->podPayTab(),
                        $this->geraPixTab(),
                        $this->forceOnePayTab(),
                    ]),
            ]);
    }

    /**
     * Qual gateway está ativo. Um por operação: um para depósito, um para saque.
     * Grava em settings.deposit_gateway / settings.saque.
     */
    private function activeGatewaySection(): Section
    {
        $options = \App\Services\Gateways\GatewayManager::options();

        return Section::make('Gateway ativo')
            ->description('Escolha qual gateway processa os depósitos e os saques. Só um de cada por vez.')
            ->schema([
                Forms\Components\Select::make('deposit_gateway')
                    ->label('Gateway de depósito')
                    ->options($options)
                    ->required()
                    ->native(false)
                    ->helperText('Precisa estar com as credenciais preenchidas e ligado na aba dele.'),

                Forms\Components\Select::make('saque')
                    ->label('Gateway de saque')
                    ->options($options)
                    ->required()
                    ->native(false)
                    ->helperText('Pode ser diferente do de depósito.'),
            ])
            ->columns(2);
    }

    private function digitoPayTab(): Tabs\Tab
    {
        return Tabs\Tab::make('DigitoPay')
            ->icon('heroicon-o-banknotes')
            ->schema([
                $this->gatewayInfoSection(
                    'DigitoPay',
                    'Credenciais em Configurações da conta, no painel DigitoPay.',
                    'https://www.digitopay.io',
                    1,
                    'Melhor opção do mercado. Só abre conta por indicação.',
                    null
                ),

                Section::make('Credenciais DigitoPay')
                    ->schema([
                        Forms\Components\Toggle::make('digitopay_is_enable')
                            ->label('DigitoPay ativo')
                            ->helperText('Desligado, nenhum depósito/saque sai por ele mesmo se estiver selecionado acima.')
                            ->columnSpanFull(),

                        TextInput::make('digitopay_uri')
                            ->label('API Base URL')
                            ->placeholder(\App\Services\Gateways\DigitoPay\DigitoPayClient::BASE_URL)
                            ->maxLength(191)
                            ->columnSpanFull(),

                        $this->secretField(
                            'digitopay_client_id',
                            'Client ID',
                            $this->setting?->digitopay_client_id,
                            'Valor atual carregado. Edite somente se quiser alterar.'
                        ),

                        $this->secretField(
                            'digitopay_secret',
                            'Secret',
                            $this->setting?->digitopay_secret,
                            'Só aparece uma vez no painel do provedor. Edite somente se quiser alterar.'
                        ),

                    ])
                    ->columns(2),
            ]);
        // Sem seção de callback: a URL vai em `callbackUrl` dentro de cada
        // requisição de depósito/saque — não há o que cadastrar no painel deles.
    }

    private function geraPixTab(): Tabs\Tab
    {
        return Tabs\Tab::make('GeraPix')
            ->icon('heroicon-o-qr-code')
            ->schema([
                $this->gatewayInfoSection(
                    'GeraPix',
                    'Configure API Base URL e token Bearer.',
                    'https://gerapix.digital/',
                    5,
                    'Aceita contas com facilidade.',
                    ['url' => 'https://wa.me/552137312931', 'canal' => 'WhatsApp']
                ),

                Section::make('Credenciais GeraPix')
                    ->schema([
                        TextInput::make('gerapix_uri')
                            ->label('API Base URL')
                            ->placeholder('https://api.gerapix.digital')
                            ->maxLength(191)
                            ->columnSpanFull(),

                        $this->secretField(
                            'gerapix_secret_token',
                            'Token Secreto GeraPix',
                            $this->setting?->gerapix_secret_token,
                            'Token atual carregado. Edite somente se quiser alterar.'
                        ),
                    ])
                    ->columns(2),
            ]);
    }

    private function pixupTab(): Tabs\Tab
    {
        return Tabs\Tab::make('PIXUP')
            ->icon('heroicon-o-shield-check')
            ->schema([
                $this->gatewayInfoSection(
                    'PIXUP',
                    'Credenciais no dashboard PIXUP. Atenção: são quatro segredos DIFERENTES.',
                    'https://dashboard.pixupbr.com/register/f385c7950e76a0ab2a7ab142f3c80b1f',
                    3,
                    'Aceita contas com facilidade.',
                    ['url' => 'https://wa.me/5547920034513', 'canal' => 'WhatsApp']
                ),

                Section::make('Credenciais PIXUP')
                    ->schema([
                        Forms\Components\Toggle::make('pixup_is_enable')
                            ->label('PIXUP ativo')
                            ->helperText('Desligado, nenhum depósito/saque sai por ele mesmo se estiver selecionado acima.')
                            ->columnSpanFull(),

                        TextInput::make('pixup_uri')
                            ->label('API Base URL')
                            ->placeholder(\App\Services\Gateways\Pixup\PixupClient::BASE_URL)
                            ->maxLength(191)
                            ->columnSpanFull(),

                        $this->secretField(
                            'pixup_client_id',
                            'Client ID',
                            $this->setting?->pixup_client_id,
                            'Valor atual carregado. Edite somente se quiser alterar.'
                        ),

                        $this->secretField(
                            'pixup_client_secret',
                            'Client Secret',
                            $this->setting?->pixup_client_secret,
                            'Emite o token OAuth2, junto com o Client ID.'
                        ),

                        $this->generatableSecretField(
                            'pixup_webhook_secret',
                            'Webhook Secret (opcional)',
                            $this->setting?->pixup_webhook_secret,
                            'Deixe VAZIO: hoje o painel da PIXUP não tem onde cadastrar esse segredo, e o webhook chega sem assinatura. Os depósitos são confirmados reconsultando a API deles, não pelo webhook. Só preencha se algum dia o campo aparecer no painel — e aí com o mesmo valor dos dois lados.'
                        ),
                    ])
                    ->columns(2),
            ]);
        // Sem seção de callback: a URL vai em `postback_url` dentro de cada
        // requisição de depósito/saque — não há o que cadastrar no painel deles.
    }

    private function podPayTab(): Tabs\Tab
    {
        return Tabs\Tab::make('PodPay')
            ->icon('heroicon-o-credit-card')
            ->schema([
                $this->gatewayInfoSection(
                    'PodPay',
                    'A chave secreta aparece uma única vez no dashboard. Tem sandbox para testar sem dinheiro real.',
                    'https://www.podpay.app/',
                    4,
                    'Aceita contas com facilidade.',
                    ['url' => 'https://wa.me/5511910987870', 'canal' => 'WhatsApp']
                ),

                Section::make('Credenciais PodPay')
                    ->schema([
                        Forms\Components\Toggle::make('podpay_is_enable')
                            ->label('PodPay ativo')
                            ->helperText('Desligado, nenhum depósito/saque sai por ele mesmo se estiver selecionado acima.')
                            ->columnSpanFull(),

                        TextInput::make('podpay_uri')
                            ->label('API Base URL')
                            ->placeholder(\App\Services\Gateways\PodPay\PodPayClient::BASE_URL)
                            ->maxLength(191)
                            ->helperText(new HtmlString(
                                'Produção: <code>' . e(\App\Services\Gateways\PodPay\PodPayClient::BASE_URL) . '</code><br>'
                                . 'Sandbox: <code>' . e(\App\Services\Gateways\PodPay\PodPayClient::SANDBOX_URL) . '</code> (use a chave <code>sk_test_…</code>)'
                            ))
                            ->columnSpanFull(),

                        $this->secretField(
                            'podpay_api_key',
                            'API Key (x-api-key)',
                            $this->setting?->podpay_api_key,
                            'sk_live_… em produção, sk_test_… no sandbox. Só aparece uma vez no painel deles.'
                        ),
                    ])
                    ->columns(2),
            ]);
        // Sem seção de callback: a URL vai em `postbackUrl` dentro de cada
        // requisição — não há o que cadastrar no painel deles.
    }

    /**
     * Campo de segredo com botão GERAR.
     *
     * Para segredos que NÓS definimos e o operador cola no painel do provedor
     * (ex.: o webhook secret da PIXUP). Gera 32 caracteres de propósito: este
     * valor é a chave do HMAC que autoriza confirmação de depósito — 6
     * caracteres seriam quebráveis por força bruta em segundos a partir de um
     * único webhook capturado, e aí daria para forjar confirmação sem pagar.
     * Como o operador só copia e cola, o tamanho não atrapalha ninguém.
     */
    private function generatableSecretField(string $name, string $label, ?string $configuredValue, string $helper = ''): TextInput
    {
        return $this->secretField($name, $label, $configuredValue, $helper)
            ->maxLength(191)
            ->suffixAction(
                FormAction::make('gerar_' . $name)
                    ->label('GERAR')
                    ->icon('heroicon-m-sparkles')
                    ->color('primary')
                    ->action(function (Forms\Set $set) use ($name) {
                        $set($name, Str::random(32));

                        Notification::make()
                            ->title('Segredo gerado')
                            ->body('Copie este valor e cole no painel do provedor. Depois clique em Salvar gateways.')
                            ->success()
                            ->send();
                    })
            );
    }

    private function abilityPayTab(): Tabs\Tab
    {
        return Tabs\Tab::make('AbilityPay')
            ->icon('heroicon-o-building-library')
            ->schema([
                $this->gatewayInfoSection(
                    'AbilityPay',
                    'Crie a conta, aguarde a aprovação e gere as credenciais em API → Credenciais. O saque exige KYC aprovado no painel deles.',
                    'https://abilitypay.app/register?ref=EK7WRN58',
                    2,
                    'Aceita contas com facilidade.',
                    ['url' => 'https://t.me/Micaelasilva7', 'canal' => 'Telegram']
                ),

                Section::make('Credenciais AbilityPay')
                    ->schema([
                        Forms\Components\Toggle::make('abilitypay_is_enable')
                            ->label('AbilityPay ativo')
                            ->helperText('Desligado, nenhum depósito/saque sai por ele mesmo se estiver selecionado acima.')
                            ->columnSpanFull(),

                        TextInput::make('abilitypay_uri')
                            ->label('API Base URL')
                            ->placeholder(\App\Services\Gateways\AbilityPay\AbilityPayClient::BASE_URL)
                            ->maxLength(191)
                            ->columnSpanFull(),

                        $this->secretField(
                            'abilitypay_client_id',
                            'X-Client-Id',
                            $this->setting?->abilitypay_client_id,
                            'Gerado no painel deles, em API → Credenciais.'
                        ),

                        $this->secretField(
                            'abilitypay_client_secret',
                            'X-Client-Secret',
                            $this->setting?->abilitypay_client_secret,
                            'Gerado junto com o Client Id.'
                        ),
                    ])
                    ->columns(2),

                // ESTE gateway precisa da URL no painel: diferente dos outros, ele
                // não recebe o callback na requisição — a URL é cadastrada no
                // painel deles no momento de criar a chave de API.
                Section::make('URL de callback')
                    ->description('Informe esta URL no campo de callback ao criar a chave de API no painel do AbilityPay. Sem ela, nenhum depósito é confirmado.')
                    ->schema([
                        Forms\Components\Placeholder::make('abilitypay_cb')
                            ->label('Callback (depósitos e saques)')
                            ->content(url('/api/abilitypay/callback')),
                    ]),
            ]);
    }

    private function forceOnePayTab(): Tabs\Tab
    {
        return Tabs\Tab::make('ForceOnePay')
            ->icon('heroicon-o-bolt')
            ->schema([
                $this->gatewayInfoSection(
                    'ForceOnePay',
                    'Use o token contratado com a ForceOnePay. A API deles só paga saque para chave CPF/CNPJ.',
                    'https://forceonepay.com.br/',
                    6,
                    'Aceita contas com facilidade.',
                    null
                ),

                Section::make('Credenciais ForceOnePay')
                    ->schema([
                        Forms\Components\Toggle::make('forceonepay_is_enable')
                            ->label('ForceOnePay ativo')
                            ->helperText('Desligado, nenhum depósito/saque sai por ele mesmo se estiver selecionado acima.')
                            ->columnSpanFull(),

                        TextInput::make('forceonepay_uri')
                            ->label('API Base URL')
                            ->placeholder(\App\Services\Gateways\ForceOnePay\ForceOnePayClient::BASE_URL)
                            ->maxLength(191)
                            ->columnSpanFull(),

                        $this->secretField(
                            'forceonepay_token',
                            'Token contratado',
                            $this->setting?->forceonepay_token,
                            'O token que a ForceOnePay forneceu. É com ele que o sistema gera o Bearer de trabalho.'
                        ),
                    ])
                    ->columns(2),

                Section::make('Como este gateway é protegido')
                    ->description(new HtmlString(
                        'A API da ForceOnePay <strong>não tem consulta de transação nem assinatura de webhook</strong> — '
                        . 'diferente dos outros gateways, não dá para perguntar a ela se o PIX entrou mesmo. '
                        . 'Por isso o sistema gera uma <strong>URL de webhook secreta e única para cada transação</strong>, '
                        . 'e só credita quando o segredo, o txid e o valor batem com os da nossa base. '
                        . 'Não há nada a cadastrar no painel deles: a URL vai em cada requisição.'
                    ))
                    ->schema([]),
            ]);
    }

    /**
     * Cabeçalho da aba: onde criar a conta e com quem falar.
     *
     * O cliente que abre esta página normalmente ainda NÃO tem conta em gateway
     * nenhum e não sabe qual escolher. Então a aba abre com a posição do provedor
     * na recomendação, o botão de criar conta e o contato do gerente — que é o
     * que destrava a conta quando ela trava na aprovação.
     *
     * $manager: ['url' => ..., 'canal' => 'WhatsApp'|'Telegram'] ou null quando
     * o provedor não tem contato direto (aí dizemos isso, em vez de deixar o
     * cliente procurando um botão que não existe).
     */
    private function gatewayInfoSection(
        string $name,
        string $description,
        string $officialUrl,
        int $posicao,
        string $notaPosicao,
        ?array $manager = null
    ): Section {
        $slug = strtolower($name);

        $botao = static fn (string $url, string $texto, string $cor): string =>
            '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer"'
            . ' style="display:inline-flex;align-items:center;justify-content:center;padding:9px 13px;'
            . 'border-radius:12px;background:' . $cor . ';color:#fff;font-weight:800;font-size:13px;'
            . 'text-decoration:none;margin-right:8px">' . e($texto) . '</a>';

        // #1 é destaque; do #2 em diante é cinza. A cor faz o cliente ler a
        // ordem sem precisar comparar as abas uma a uma.
        $corPosicao = $posicao === 1 ? '#00b91e' : '#6b7280';

        $links = $botao($officialUrl, 'CRIAR CONTA', '#00b91e');

        if ($manager !== null) {
            $links .= $botao(
                $manager['url'],
                'FALAR COM O GERENTE (' . strtoupper($manager['canal']) . ')',
                $manager['canal'] === 'Telegram' ? '#229ed9' : '#25d366'
            );
        } else {
            $links .= '<span style="display:inline-flex;align-items:center;padding:9px 13px;'
                . 'border-radius:12px;background:#e5e7eb;color:#6b7280;font-weight:700;font-size:13px">'
                . 'Sem contato de gerente</span>';
        }

        return Section::make($name . ' — informações rápidas')
            ->description($description)
            ->schema([
                Forms\Components\Placeholder::make($slug . '_ranking')
                    ->label('Recomendação')
                    ->content(new HtmlString(
                        '<span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;'
                        . 'background:' . $corPosicao . ';color:#fff;font-weight:800;font-size:12px;margin-right:8px">'
                        . '#' . $posicao . '</span>'
                        . '<span style="font-size:13px">' . e($notaPosicao) . '</span>'
                    )),

                // "CRIAR CONTA" em vez de "Abrir site oficial": o operador que
                // abre esta aba ainda não tem conta no provedor — o rótulo diz o
                // que ele precisa fazer, não para onde o link aponta.
                Forms\Components\Placeholder::make($slug . '_official_link')
                    ->label('Ainda não tem conta?')
                    ->content(new HtmlString($links)),
            ])
            ->columns(1);
    }

    private function secretField(string $name, string $label, ?string $configuredValue, string $helper = ''): TextInput
    {
        $field = TextInput::make($name)
            ->label($label)
            ->default($configuredValue)
            ->placeholder('Digite o valor')
            ->maxLength(191)
            ->columnSpanFull()
            ->hint(fn () => $this->configuredHint($configuredValue))
            ->hintColor(fn () => filled($configuredValue) ? 'success' : 'danger');

        if ($helper !== '') {
            $field->helperText($helper);
        }

        return $field;
    }

    private function tokenField(string $name, string $label, ?string $configuredValue, string $helper = ''): TextInput
    {
        $field = TextInput::make($name)
            ->label($label)
            ->default($configuredValue)
            ->placeholder('Digite ou gere um token')
            ->maxLength(191)
            ->columnSpanFull()
            ->suffixAction(
                FormAction::make('generate_' . $name)
                    ->label('Gerar token')
                    ->icon('heroicon-m-sparkles')
                    ->action(fn (Forms\Set $set) => $set($name, Str::random(24)))
            )
            ->hint(fn () => $this->configuredHint($configuredValue))
            ->hintColor(fn () => filled($configuredValue) ? 'success' : 'warning');

        if ($helper !== '') {
            $field->helperText($helper);
        }

        return $field;
    }

    private function copyAction(string $name, string $value): FormAction
    {
        return FormAction::make($name)
            ->label('Copiar')
            ->icon('heroicon-m-clipboard')
            ->extraAttributes([
                'x-on:click' => 'navigator.clipboard.writeText(' . json_encode($value) . ')',
            ])
            ->action(function () use ($value): void {
                Notification::make()
                    ->title('Copiado')
                    ->body($value)
                    ->success()
                    ->send();
            });
    }

    private function configuredHint(?string $value): HtmlString
    {
        $ok = filled($value);

        $dot = $ok
            ? '<span style="display:inline-block;width:8px;height:8px;border-radius:999px;background:#22c55e;margin-right:6px;"></span>'
            : '<span style="display:inline-block;width:8px;height:8px;border-radius:999px;background:#ef4444;margin-right:6px;"></span>';

        $text = $ok ? 'Configurado' : 'Não configurado';
        $badge = $ok
            ? '<span style="margin-left:8px;padding:2px 8px;border-radius:999px;background:rgba(34,197,94,.15);color:#22c55e;font-weight:700;font-size:12px;">OK</span>'
            : '<span style="margin-left:8px;padding:2px 8px;border-radius:999px;background:rgba(239,68,68,.15);color:#ef4444;font-weight:700;font-size:12px;">FALTA</span>';

        return new HtmlString($dot . $text . $badge);
    }

    public function submit(array $confirmation = []): void
    {
        try {
            if (config('app.demo')) {
                Notification::make()
                    ->title('Atenção')
                    ->body('Você não pode realizar esta alteração na versão demo.')
                    ->danger()
                    ->send();

                return;
            }

            if (! app(AdminActionGuard::class)->confirm((string) ($confirmation['admin_password'] ?? ''))) {
                Notification::make()
                    ->title('Acesso negado')
                    ->body('O PIN administrativo está incorreto.')
                    ->danger()
                    ->send();

                return;
            }

            $payload = $this->form->getState();

            // O seletor de gateway ativo e as flags moram em `settings`, não em
            // `gateways` (que só guarda credenciais). Separa antes de gravar,
            // senão o update falha por coluna inexistente.
            $settingKeys = ['deposit_gateway', 'saque', 'digitopay_is_enable', 'pixup_is_enable', 'podpay_is_enable', 'abilitypay_is_enable', 'forceonepay_is_enable'];
            $settingsPayload = array_intersect_key($payload, array_flip($settingKeys));
            $payload = array_diff_key($payload, array_flip($settingKeys));

            if ($settingsPayload !== []) {
                foreach (['digitopay_is_enable', 'pixup_is_enable', 'podpay_is_enable', 'abilitypay_is_enable', 'forceonepay_is_enable'] as $flag) {
                    if (array_key_exists($flag, $settingsPayload)) {
                        $settingsPayload[$flag] = ! empty($settingsPayload[$flag]) ? 1 : 0;
                    }
                }
                \App\Models\Setting::query()->first()?->update($settingsPayload);
            }

            $gateway = Gateway::query()->first();

            if ($gateway) {
                $gateway->update($payload);
            } else {
                Gateway::query()->create($payload);
            }
            // Core::getSetting() memoiza numa estática por request, então o
            // próximo request já lê o gateway novo — nada a invalidar aqui.

            Notification::make()
                ->title('Gateways atualizados')
                ->body('Credenciais salvas com segurança.')
                ->success()
                ->send();

            $this->mount();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Erro ao salvar gateways')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function stats(): array
    {
        $gateway = $this->setting ?? Gateway::query()->first();

        return [
            'configured' => collect($this->gatewayStatus($gateway))->where('configured', true)->count(),
            'total' => count($this->gatewayStatus($gateway)),
            'last_update' => $gateway?->updated_at,
        ];
    }

    public function gatewayStatus(?Gateway $gateway = null): array
    {
        $gateway = $gateway ?? $this->setting ?? Gateway::query()->first();

        return [
            'GeraPix' => [
                'configured' => filled($gateway?->gerapix_uri) && filled($gateway?->gerapix_secret_token),
                'base_url' => $gateway?->gerapix_uri ?: '-',
            ],
        ];
    }

    private function secretFields(): array
    {
        return [
            'gerapix_secret_token',
        ];
    }

    public function webhookUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($baseUrl === '') {
            $baseUrl = rtrim(url('/'), '/');
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }
}