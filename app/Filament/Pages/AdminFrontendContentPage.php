<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\AdminAudit;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
            // Mantido somente como fallback técnico. O Hero editável fica exclusivamente em Banners da Plataforma.
            'home_hero_image' => '/pixfacil-v15/art/home-welcome.webp',
            'home_vip_image' => '/pixfacil-v15/art/home-vip.webp',
            'home_pix_image' => '/pixfacil-v15/art/home-pix.webp',
            'home_promotions_image' => '/pixfacil-v15/art/home-promotions.webp',
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
        $setting = $this->setting();
        $this->form->fill([
            'frontend_content' => array_replace(static::defaults(), $setting->frontend_content ?? []),
            'home_vip_upload' => null,
            'home_pix_upload' => null,
            'home_promotions_upload' => null,
            'reset_home_art' => false,
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Tabs::make('Conteúdo')->persistTabInQueryString()->tabs([
                Forms\Components\Tabs\Tab::make('Home')->icon('heroicon-o-home')->schema([
                    Forms\Components\Section::make('Fonte oficial da Home')
                        ->description('Cada coisa tem um único lugar de edição para evitar conflito de configuração.')
                        ->schema([
                            Forms\Components\Placeholder::make('home_banner_source')->label('Hero / Banner principal')->content(new HtmlString(
                                'Gerenciado exclusivamente em <a href="'.e(AdminBannersPage::getUrl()).'" style="color:#34d399;font-weight:800">Banners da Plataforma</a>. Ordem, Desktop e Mobile ficam lá.'
                            )),
                            Forms\Components\Placeholder::make('home_sections_note')->label('Seções de jogos')->content('Título, subtítulo, ordem e jogos são controlados em Seções da Home.'),
                            Forms\Components\TextInput::make('frontend_content.brand_tagline')->label('Slogan da plataforma')->maxLength(140)->columnSpanFull(),
                        ])->columns(2),

                    Forms\Components\Section::make('Artes auxiliares da Home')
                        ->description('Estas são as artes laterais. A imagem atual aparece abaixo; envie outra apenas para substituir.')
                        ->schema([
                            $this->artPreview('vip_preview', 'VIP atual', 'home_vip_image'),
                            $this->artUpload('home_vip_upload', 'Substituir card VIP', '900x480 ou proporção próxima de 1,9:1.'),
                            $this->artPreview('pix_preview', 'PIX atual', 'home_pix_image'),
                            $this->artUpload('home_pix_upload', 'Substituir card PIX', '900x480 ou proporção próxima de 1,9:1.'),
                            $this->artPreview('promotions_preview', 'Promoções atual', 'home_promotions_image'),
                            $this->artUpload('home_promotions_upload', 'Substituir arte de Promoções', '900x480 ou proporção próxima de 1,9:1.'),
                            Forms\Components\Toggle::make('reset_home_art')->label('Restaurar artes auxiliares padrão ao salvar')->columnSpanFull(),
                        ])->columns(2),

                    Forms\Components\Section::make('Card VIP lateral')->schema([
                        Forms\Components\TextInput::make('frontend_content.home_vip_kicker')->label('Selo')->maxLength(80),
                        Forms\Components\TextInput::make('frontend_content.home_vip_title')->label('Título')->maxLength(140),
                        Forms\Components\TextInput::make('frontend_content.home_vip_subtitle')->label('Subtítulo')->maxLength(180)->columnSpanFull(),
                    ])->columns(2),
                    Forms\Components\Section::make('Card PIX lateral')->schema([
                        Forms\Components\TextInput::make('frontend_content.home_pix_kicker')->label('Selo')->maxLength(80),
                        Forms\Components\TextInput::make('frontend_content.home_pix_title')->label('Título')->maxLength(140),
                        Forms\Components\TextInput::make('frontend_content.home_pix_subtitle')->label('Subtítulo')->maxLength(180)->columnSpanFull(),
                    ])->columns(2),
                    Forms\Components\Section::make('Blocos laterais')->schema([
                        Forms\Components\TextInput::make('frontend_content.home_promotions_title')->label('Título de Promoções')->maxLength(100),
                        Forms\Components\TextInput::make('frontend_content.home_live_title')->label('Título de Ganhos ao vivo')->maxLength(100),
                    ])->columns(2),
                ]),

                Forms\Components\Tabs\Tab::make('Marca e acesso')->icon('heroicon-o-sparkles')->schema([
                    $this->textSection('Login', 'login', true),
                    $this->textSection('Cadastro', 'register', true),
                    $this->textSection('Recuperação de senha', 'forgot'),
                ]),

                Forms\Components\Tabs\Tab::make('Carteira e conta')->icon('heroicon-o-wallet')->schema([
                    $this->pageSection('Minha Conta', 'profile'),
                    $this->pageSection('Depósito', 'deposit', true),
                    $this->pageSection('Saque', 'withdraw', true),
                    $this->pageSection('Transações', 'transactions'),
                    $this->pageSection('Minhas Apostas', 'bets'),
                    $this->pageSection('Verificação / KYC', 'kyc'),
                ]),

                Forms\Components\Tabs\Tab::make('Benefícios')->icon('heroicon-o-gift')->schema([
                    $this->pageSection('Bônus', 'bonus'),
                    $this->pageSection('VIP', 'vip'),
                    $this->pageSection('Missões', 'missions'),
                    $this->pageSection('Afiliados', 'affiliate'),
                ]),

                Forms\Components\Tabs\Tab::make('Suporte e responsabilidade')->icon('heroicon-o-lifebuoy')->schema([
                    $this->pageSection('Suporte', 'support'),
                    Forms\Components\Section::make('Canais de suporte')->schema([
                        Forms\Components\TextInput::make('frontend_content.support_whatsapp')->label('WhatsApp oficial')->maxLength(40),
                        Forms\Components\TextInput::make('frontend_content.support_email')->label('E-mail oficial')->email()->maxLength(191),
                    ])->columns(2),
                    $this->pageSection('Jogo Responsável', 'responsible'),
                    Forms\Components\Textarea::make('frontend_content.footer_text')->label('Texto do rodapé')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    private function artPreview(string $name, string $label, string $contentKey): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make($name)->label($label)->content(function () use ($contentKey): HtmlString {
            $content = array_replace(static::defaults(), $this->setting()->frontend_content ?? []);
            $url = $this->assetUrl($content[$contentKey] ?? null);
            if (! $url) return new HtmlString('<span style="color:#9ca3af">Sem arte configurada</span>');
            return new HtmlString('<div style="max-width:420px;border:1px solid rgba(52,211,153,.24);border-radius:14px;overflow:hidden;background:#020403"><img src="'.e($url).'" alt="'.e($label).'" style="display:block;width:100%;aspect-ratio:1.875/1;object-fit:cover"></div>');
        });
    }

    private function artUpload(string $name, string $label, string $helper): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($name)
            ->label($label)->image()->helperText($helper . ' Se vazio, mantém a atual.')
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null);
    }

    private function textSection(string $label, string $key, bool $withBadge = false): Forms\Components\Section
    {
        $fields = [];
        if ($withBadge) $fields[] = Forms\Components\TextInput::make("frontend_content.{$key}_badge")->label('Selo / badge')->maxLength(80);
        $fields[] = Forms\Components\TextInput::make("frontend_content.{$key}_title")->label('Título')->maxLength(140);
        $fields[] = Forms\Components\Textarea::make("frontend_content.{$key}_subtitle")->label('Subtítulo')->rows(2)->columnSpanFull();
        return Forms\Components\Section::make($label)->schema($fields)->columns(2);
    }

    private function pageSection(string $label, string $key, bool $withHelp = false): Forms\Components\Section
    {
        $fields = [
            Forms\Components\TextInput::make("frontend_content.{$key}_title")->label('Título')->maxLength(140),
            Forms\Components\Textarea::make("frontend_content.{$key}_subtitle")->label('Subtítulo')->rows(2),
        ];
        if ($withHelp) $fields[] = Forms\Components\Textarea::make("frontend_content.{$key}_help")->label('Texto de ajuda')->rows(2)->columnSpanFull();
        return Forms\Components\Section::make($label)->schema($fields)->columns(2)->collapsed();
    }

    public function save(): void
    {
        $setting = $this->setting();
        $state = $this->form->getState();
        $existing = array_replace(static::defaults(), $setting->frontend_content ?? []);
        $content = array_replace($existing, $state['frontend_content'] ?? []);
        $before = $existing;

        $artMap = [
            'home_vip_upload' => 'home_vip_image',
            'home_pix_upload' => 'home_pix_image',
            'home_promotions_upload' => 'home_promotions_image',
        ];

        if ((bool) ($state['reset_home_art'] ?? false)) {
            $defaults = static::defaults();
            foreach ($artMap as $contentKey) $content[$contentKey] = $defaults[$contentKey];
        } else {
            foreach ($artMap as $uploadKey => $contentKey) {
                $path = $this->extractUploadPath($state[$uploadKey] ?? null);
                if (filled($path)) $content[$contentKey] = $path;
            }
        }

        // Hero nunca é alterado por esta tela; Banners da Plataforma é a fonte editável oficial.
        $content['home_hero_image'] = $existing['home_hero_image'] ?? static::defaults()['home_hero_image'];

        $setting->update(['frontend_content' => $content]);
        AdminAudit::log('frontend.content.update', $setting, $this->auditSubset($before), $this->auditSubset($content), 'Atualizou conteúdo e artes auxiliares do frontend');

        Cache::forget('api:presentation:v1');
        Cache::forget('setting');
        Cache::put('setting', $setting->fresh());
        Cache::put('asset_version', 'v' . now()->timestamp);

        Notification::make()->title('Conteúdo atualizado')->body('Textos e artes auxiliares foram salvos.')->success()->send();
        $this->mount();
    }

    private function setting(): Setting
    {
        return Setting::query()->firstOrCreate(['id' => 1], ['software_name' => config('app.name', 'Plataforma')]);
    }

    private function auditSubset(array $content): array
    {
        return array_intersect_key($content, array_flip([
            'brand_tagline','home_vip_image','home_pix_image','home_promotions_image','home_vip_title','home_pix_title',
            'login_title','register_title','profile_title','deposit_title','withdraw_title','bonus_title','vip_title','missions_title',
            'transactions_title','bets_title','kyc_title','affiliate_title','support_title','responsible_title','footer_text',
        ]));
    }

    private function extractUploadPath(mixed $value): ?string
    {
        if (is_string($value) && filled($value)) return ltrim($value, '/');
        if (is_array($value)) foreach ($value as $item) if ($path = $this->extractUploadPath($item)) return $path;
        return null;
    }

    private function assetUrl(?string $value): ?string
    {
        if (blank($value)) return null;
        $value = ltrim((string) $value, '/');
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) return $value;
        if (str_starts_with($value, 'storage/')) return asset($value);
        if (str_starts_with($value, 'pixfacil-v15/')) return asset($value);
        return asset('storage/' . $value);
    }
}
