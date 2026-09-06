<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class AdminFrontendContentPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.admin-frontend-content-page';
    protected static ?string $title = 'Conteúdo das Telas';
    protected static ?string $navigationLabel = 'Conteúdo das Telas';
    protected static ?string $navigationGroup = 'Tema e Aparência';
    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'conteudo-das-telas';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function defaults(): array
    {
        return [
            'brand_tagline' => 'Sua diversão em outro nível',
            'home_vip_kicker' => 'SEJA VIP',
            'home_vip_title' => 'Mais benefícios',
            'home_vip_subtitle' => 'Mais recompensas',
            'home_pix_kicker' => 'PIX RÁPIDO',
            'home_pix_title' => 'Seguro',
            'home_pix_subtitle' => 'Rápido e simples',
            'home_promotions_title' => 'Promoções',
            'home_live_title' => 'Ganhos ao vivo',
            'login_badge' => 'Área segura',
            'login_title' => 'Entre com sua conta',
            'login_subtitle' => 'Acesse sua conta e continue jogando seus jogos favoritos.',
            'register_badge' => 'Crie sua conta',
            'register_title' => 'Comece agora',
            'register_subtitle' => 'Crie sua conta em poucos passos e aproveite a plataforma.',
            'forgot_title' => 'Recuperar acesso',
            'forgot_subtitle' => 'Informe seu e-mail para continuar a recuperação da conta.',
            'profile_title' => 'Minha Conta',
            'profile_subtitle' => 'Gerencie sua carteira, segurança e preferências.',
            'deposit_title' => 'Depositar',
            'deposit_subtitle' => 'Adicione saldo com PIX de forma rápida e segura.',
            'deposit_help' => 'Confira o valor, gere o PIX e aguarde a confirmação automática.',
            'withdraw_title' => 'Sacar',
            'withdraw_subtitle' => 'Solicite seu saque para uma chave PIX vinculada à sua conta.',
            'withdraw_help' => 'Os limites e regras exibidos são os mesmos definidos no Admin.',
            'bonus_title' => 'Bônus',
            'bonus_subtitle' => 'Veja ofertas, recompensas e benefícios disponíveis.',
            'vip_title' => 'VIP',
            'vip_subtitle' => 'Acompanhe seu nível e os benefícios da sua categoria.',
            'missions_title' => 'Missões',
            'missions_subtitle' => 'Complete objetivos e acompanhe seu progresso.',
            'transactions_title' => 'Transações',
            'transactions_subtitle' => 'Acompanhe depósitos, saques e movimentações da sua carteira.',
            'bets_title' => 'Minhas Apostas',
            'bets_subtitle' => 'Consulte seu histórico de jogos e resultados.',
            'kyc_title' => 'Verificação da Conta',
            'kyc_subtitle' => 'Mantenha seus dados e documentos atualizados para usar todos os recursos.',
            'affiliate_title' => 'Afiliados',
            'affiliate_subtitle' => 'Acompanhe seus indicados, comissões e desempenho.',
            'support_title' => 'Suporte',
            'support_subtitle' => 'Encontre ajuda e canais oficiais de atendimento.',
            'support_whatsapp' => '',
            'support_email' => '',
            'responsible_title' => 'Jogo Responsável',
            'responsible_subtitle' => 'Diversão com consciência, controle e segurança.',
            'footer_text' => 'Jogue com responsabilidade. Apenas para maiores de 18 anos.',
        ];
    }

    public function mount(): void
    {
        $setting = Setting::query()->firstOrCreate(['id' => 1], [
            'software_name' => config('app.name', 'Plataforma'),
        ]);

        $this->form->fill([
            'frontend_content' => array_replace(static::defaults(), $setting->frontend_content ?? []),
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Tabs::make('Conteúdo')
                    ->persistTabInQueryString()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Home')
                            ->icon('heroicon-o-home')
                            ->schema([
                                Forms\Components\Section::make('Identidade')
                                    ->description('Logo e banners são gerenciados em Tema e Aparência. Aqui você controla os textos da Home.')
                                    ->schema([
                                        Forms\Components\TextInput::make('frontend_content.brand_tagline')->label('Slogan da plataforma')->maxLength(140)->columnSpanFull(),
                                    ]),
                                Forms\Components\Section::make('Card VIP lateral')
                                    ->schema([
                                        Forms\Components\TextInput::make('frontend_content.home_vip_kicker')->label('Selo')->maxLength(80),
                                        Forms\Components\TextInput::make('frontend_content.home_vip_title')->label('Título')->maxLength(140),
                                        Forms\Components\TextInput::make('frontend_content.home_vip_subtitle')->label('Subtítulo')->maxLength(180)->columnSpanFull(),
                                    ])->columns(2),
                                Forms\Components\Section::make('Card PIX lateral')
                                    ->schema([
                                        Forms\Components\TextInput::make('frontend_content.home_pix_kicker')->label('Selo')->maxLength(80),
                                        Forms\Components\TextInput::make('frontend_content.home_pix_title')->label('Título')->maxLength(140),
                                        Forms\Components\TextInput::make('frontend_content.home_pix_subtitle')->label('Subtítulo')->maxLength(180)->columnSpanFull(),
                                    ])->columns(2),
                                Forms\Components\Section::make('Blocos laterais')
                                    ->schema([
                                        Forms\Components\TextInput::make('frontend_content.home_promotions_title')->label('Título de Promoções')->maxLength(100),
                                        Forms\Components\TextInput::make('frontend_content.home_live_title')->label('Título de Ganhos ao vivo')->maxLength(100),
                                    ])->columns(2),
                                Forms\Components\Placeholder::make('home_sections_note')
                                    ->label('Seções de jogos')
                                    ->content('Os títulos, subtítulos, ordem e jogos das seções da Home continuam sendo controlados no módulo de Home Sections do Admin.'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Marca e acesso')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                Forms\Components\Section::make('Login')
                                    ->schema([
                                        Forms\Components\TextInput::make('frontend_content.login_badge')->label('Selo / badge')->maxLength(80),
                                        Forms\Components\TextInput::make('frontend_content.login_title')->label('Título')->maxLength(140),
                                        Forms\Components\Textarea::make('frontend_content.login_subtitle')->label('Subtítulo')->rows(2)->columnSpanFull(),
                                    ])->columns(2),
                                Forms\Components\Section::make('Cadastro')
                                    ->schema([
                                        Forms\Components\TextInput::make('frontend_content.register_badge')->label('Selo / badge')->maxLength(80),
                                        Forms\Components\TextInput::make('frontend_content.register_title')->label('Título')->maxLength(140),
                                        Forms\Components\Textarea::make('frontend_content.register_subtitle')->label('Subtítulo')->rows(2)->columnSpanFull(),
                                    ])->columns(2),
                                Forms\Components\Section::make('Recuperação de senha')
                                    ->schema([
                                        Forms\Components\TextInput::make('frontend_content.forgot_title')->label('Título')->maxLength(140),
                                        Forms\Components\Textarea::make('frontend_content.forgot_subtitle')->label('Subtítulo')->rows(2),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Carteira e conta')
                            ->icon('heroicon-o-wallet')
                            ->schema([
                                $this->pageSection('Minha Conta', 'profile'),
                                $this->pageSection('Depósito', 'deposit', true),
                                $this->pageSection('Saque', 'withdraw', true),
                                $this->pageSection('Transações', 'transactions'),
                                $this->pageSection('Minhas Apostas', 'bets'),
                                $this->pageSection('Verificação / KYC', 'kyc'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Benefícios')
                            ->icon('heroicon-o-gift')
                            ->schema([
                                $this->pageSection('Bônus', 'bonus'),
                                $this->pageSection('VIP', 'vip'),
                                $this->pageSection('Missões', 'missions'),
                                $this->pageSection('Afiliados', 'affiliate'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Suporte e responsabilidade')
                            ->icon('heroicon-o-lifebuoy')
                            ->schema([
                                $this->pageSection('Suporte', 'support'),
                                Forms\Components\Section::make('Canais de suporte')
                                    ->schema([
                                        Forms\Components\TextInput::make('frontend_content.support_whatsapp')->label('WhatsApp oficial')->placeholder('Ex.: 5567999999999')->maxLength(40),
                                        Forms\Components\TextInput::make('frontend_content.support_email')->label('E-mail oficial')->email()->maxLength(191),
                                    ])->columns(2),
                                $this->pageSection('Jogo Responsável', 'responsible'),
                                Forms\Components\Textarea::make('frontend_content.footer_text')->label('Texto do rodapé')->rows(2)->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private function pageSection(string $label, string $key, bool $withHelp = false): Forms\Components\Section
    {
        $fields = [
            Forms\Components\TextInput::make("frontend_content.{$key}_title")->label('Título')->maxLength(140),
            Forms\Components\Textarea::make("frontend_content.{$key}_subtitle")->label('Subtítulo')->rows(2),
        ];

        if ($withHelp) {
            $fields[] = Forms\Components\Textarea::make("frontend_content.{$key}_help")->label('Texto de ajuda')->rows(2)->columnSpanFull();
        }

        return Forms\Components\Section::make($label)->schema($fields)->columns(2)->collapsed();
    }

    public function save(): void
    {
        $setting = Setting::query()->firstOrCreate(['id' => 1], [
            'software_name' => config('app.name', 'Plataforma'),
        ]);

        $state = $this->form->getState();
        $content = array_replace(static::defaults(), $state['frontend_content'] ?? []);
        $setting->update(['frontend_content' => $content]);

        Cache::forget('api:presentation:v1');
        Cache::put('setting', $setting->fresh());
        Cache::put('asset_version', 'v' . now()->timestamp);

        Notification::make()
            ->title('Conteúdo atualizado')
            ->body('Os textos das telas foram salvos e passam a valer no frontend após atualizar a página.')
            ->success()
            ->send();

        $this->mount();
    }
}
