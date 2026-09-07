<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\AdminActionGuard;
use App\Support\AdminAudit;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminPlatformSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.admin-platform-settings-page';
    protected static ?string $title = 'Configurações Primárias';
    protected static ?string $navigationLabel = 'Configurações Primárias';
    protected static ?string $navigationGroup = 'Configurações da Plataforma';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'configuracoes-primarias';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $setting = $this->record();
        $data = $setting->toArray();

        foreach ($this->uploadFields() as $field) {
            $data[$field] = $this->normalizeFileUploadStateForForm($data[$field] ?? null);
        }

        $data['admin_pin'] = null;
        $this->form->fill($data);
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Tabs::make('Configurações')->persistTabInQueryString()->tabs([
                Forms\Components\Tabs\Tab::make('Logotipo e SEO')->icon('heroicon-o-computer-desktop')->schema([
                    Forms\Components\Section::make('Identidade da plataforma')
                        ->description('A identidade oficial usada no site e no Admin. Banners da Home são gerenciados em Tema e Aparência → Banners da Plataforma.')
                        ->schema([
                            Forms\Components\TextInput::make('software_name')->label('Nome da plataforma')->required()->maxLength(191)->columnSpanFull(),
                            Forms\Components\FileUpload::make('software_favicon')->label('Favicon')->image()->helperText('PNG quadrado recomendado.')->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null),
                            Forms\Components\FileUpload::make('software_logo_white')->label('Logo principal')->image()->helperText('PNG/WebP transparente e horizontal.')->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null),
                            Forms\Components\FileUpload::make('software_logo_black')->label('Logo de carregamento')->image()->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null),
                            Forms\Components\FileUpload::make('pixfacil_mobile_logo')->label('Logo mobile')->image()->helperText('Se vazio, usa a logo principal.')->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null),
                            Forms\Components\FileUpload::make('pixfacil_loading_logo')->label('Logo de loading mobile')->image()->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null),
                            Forms\Components\Placeholder::make('banner_source')->label('Banner principal')->content('Gerencie banners, ordem e exibição Desktop/Mobile em “Banners da Plataforma”. O campo antigo de banner mobile não é mais editável aqui.'),
                        ])->columns(3),
                    Forms\Components\Section::make('SEO básico')->schema([
                        Forms\Components\Textarea::make('meta_description')->label('Meta description')->rows(3),
                        Forms\Components\TextInput::make('meta_keywords')->label('Meta keywords')->maxLength(255),
                        Forms\Components\TextInput::make('site_url')->label('URL base / canonical')->url()->maxLength(255),
                        Forms\Components\Toggle::make('allow_indexing')->label('Permitir indexação'),
                    ])->columns(2),
                    Forms\Components\Section::make('Open Graph e Twitter')->schema([
                        Forms\Components\TextInput::make('og_title')->label('OG title')->maxLength(255),
                        Forms\Components\Textarea::make('og_description')->label('OG description')->rows(3),
                        Forms\Components\TextInput::make('twitter_title')->label('Twitter title')->maxLength(255),
                        Forms\Components\Textarea::make('twitter_description')->label('Twitter description')->rows(3),
                    ])->columns(2),
                ]),

                Forms\Components\Tabs\Tab::make('Roll-over')->icon('heroicon-o-shield-check')->schema([
                    Forms\Components\Section::make('Proteção de bônus e depósito')->schema([
                        Forms\Components\TextInput::make('rollover_deposit')->label('Roll-over depósito')->numeric()->default(1)->suffix('x'),
                        Forms\Components\TextInput::make('rollover')->label('Roll-over bônus')->numeric()->default(1)->suffix('x'),
                        Forms\Components\Toggle::make('disable_rollover')->label('Desativar rollover'),
                    ])->columns(3),
                ]),

                Forms\Components\Tabs\Tab::make('Limites de saque')->icon('heroicon-o-hand-raised')->schema([
                    Forms\Components\Section::make('Limite por período')->schema([
                        Forms\Components\TextInput::make('withdrawal_limit')->label('Limite de saque')->prefix('R$')->numeric(),
                        Forms\Components\Select::make('withdrawal_period')->label('Período')->options(['daily'=>'Dia','weekly'=>'Semana','monthly'=>'Mês','yearly'=>'Ano'])->native(false),
                    ])->columns(2),
                    Forms\Components\Section::make('Aprovação automática')->schema([
                        Forms\Components\Toggle::make('withdrawal_auto_approve')->label('Aprovar automaticamente'),
                        Forms\Components\TextInput::make('withdrawal_auto_approve_max')->label('Máximo de autoaprovação')->prefix('R$')->numeric()->default(0),
                    ])->columns(2),
                ]),

                Forms\Components\Tabs\Tab::make('Central financeira')->icon('heroicon-o-currency-dollar')->schema([
                    Forms\Components\Section::make('Gateways de pagamento')->schema([
                        Forms\Components\Placeholder::make('gateways_atalho')->label('Configuração atual')->content(fn () => new \Illuminate\Support\HtmlString(
                            '<div>Depósito: <strong>'.e(\App\Services\Gateways\GatewayManager::labelFor($this->record()->deposit_gateway)).'</strong> · Saque: <strong>'.e(\App\Services\Gateways\GatewayManager::labelFor($this->record()->saque)).'</strong></div><a href="'.e(AdminPaymentGatewaysPage::getUrl()).'" style="display:inline-flex;margin-top:10px;padding:9px 13px;border-radius:12px;background:#16a34a;color:#fff;font-weight:800;text-decoration:none">ABRIR GATEWAYS</a>'
                        )),
                    ]),
                    Forms\Components\Section::make('Depósitos e saques')->schema([
                        Forms\Components\TextInput::make('min_deposit')->label('Depósito mínimo')->prefix('R$')->numeric(),
                        Forms\Components\TextInput::make('max_deposit')->label('Depósito máximo')->prefix('R$')->numeric(),
                        Forms\Components\TextInput::make('min_withdrawal')->label('Saque mínimo')->prefix('R$')->numeric(),
                        Forms\Components\TextInput::make('max_withdrawal')->label('Saque máximo')->prefix('R$')->numeric(),
                        Forms\Components\TextInput::make('initial_bonus')->label('Bônus inicial')->numeric()->suffix('%'),
                    ])->columns(5),
                    Forms\Components\Section::make('CPA / afiliados')->schema([
                        Forms\Components\TextInput::make('cpa_baseline')->label('Depósito mínimo CPA')->prefix('R$')->numeric(),
                        Forms\Components\TextInput::make('cpa_value')->label('Percentual CPA')->numeric()->suffix('%'),
                        Forms\Components\TextInput::make('revshare_reverse')->label('Revshare reverso')->numeric()->suffix('%'),
                    ])->columns(3),
                ]),
            ]),

            Forms\Components\Section::make('Confirmação administrativa')
                ->description('Qualquer alteração nesta página exige o PIN administrativo e gera registro de auditoria.')
                ->schema([
                    Forms\Components\TextInput::make('admin_pin')->label('PIN administrativo')->password()->numeric()->length(6)->required(),
                ])->collapsed(),
        ]);
    }

    public function save(): void
    {
        if (config('app.demo')) {
            Notification::make()->title('Atenção')->body('Alterações desativadas na versão demo.')->danger()->send();
            return;
        }

        $guard = app(AdminActionGuard::class);
        $state = $this->form->getState();
        $pin = (string) ($state['admin_pin'] ?? '');
        unset($state['admin_pin']);

        if (! $guard->confirm($pin)) {
            $wait = $guard->availableIn();
            Notification::make()->title('PIN incorreto')->body($wait > 0 ? 'Muitas tentativas. Aguarde ' . $wait . ' segundo(s).' : 'Confirme seu PIN administrativo para salvar.')->danger()->send();
            return;
        }

        $setting = $this->record();
        $before = $setting->only($this->auditedFields());

        foreach ($this->uploadFields() as $field) {
            $state[$field] = $this->normalizeUploadedFileForDatabase($state[$field] ?? null);
        }

        // Campo legado mantido no banco apenas para compatibilidade, mas não é alterado por esta tela.
        unset($state['pixfacil_mobile_banner']);

        $setting->update($state);
        $fresh = $setting->fresh();

        AdminAudit::log('platform.settings.update', $fresh, $before, $fresh->only($this->auditedFields()), 'Atualizou configurações primárias da plataforma');

        Cache::put('setting', $fresh);
        Cache::forget('api:settings:index:v3');
        Cache::forget('api:settings:index:v4');
        Cache::forget('api:presentation:v1');
        Cache::forget('custom');
        Cache::forget('custom_layout');
        Cache::put('asset_version', 'v' . now()->timestamp);

        Notification::make()->title('Configurações salvas')->body('Alterações confirmadas e registradas na auditoria.')->success()->send();
        $this->mount();
    }

    public function record(): Setting
    {
        return Setting::query()->firstOrCreate(['id' => 1], [
            'software_name' => config('app.name', 'Plataforma'),
            'min_deposit' => 0, 'max_deposit' => 0, 'min_withdrawal' => 0, 'max_withdrawal' => 0,
            'rollover' => 1, 'rollover_deposit' => 1, 'disable_rollover' => false,
            'withdrawal_period' => 'daily', 'withdrawal_limit' => 0,
            'withdrawal_auto_approve' => false, 'withdrawal_auto_approve_max' => 0,
            'allow_indexing' => false,
        ]);
    }

    public function stats(): array
    {
        $s = $this->record();
        return [
            'name' => $s->software_name ?: '-',
            'deposit_gateway' => \App\Services\Gateways\GatewayManager::labelFor($s->deposit_gateway),
            'withdraw_gateway' => \App\Services\Gateways\GatewayManager::labelFor($s->saque),
            'min_deposit' => $this->money($s->min_deposit),
            'min_withdrawal' => $this->money($s->min_withdrawal),
            'rollover' => (float) $s->rollover,
            'rollover_deposit' => (float) $s->rollover_deposit,
            'rollover_disabled' => (bool) $s->disable_rollover,
            'auto_approve' => (bool) $s->withdrawal_auto_approve,
            'auto_approve_max' => $this->money($s->withdrawal_auto_approve_max),
            'indexing' => (bool) $s->allow_indexing,
            'updated_at' => $s->updated_at,
            'favicon' => $this->imageUrl($s->software_favicon),
            'logo_white' => $this->imageUrl($s->software_logo_white),
            'logo_black' => $this->imageUrl($s->software_logo_black),
        ];
    }

    private function uploadFields(): array
    {
        return ['software_favicon','software_logo_white','software_logo_black','pixfacil_mobile_logo','pixfacil_loading_logo'];
    }

    private function auditedFields(): array
    {
        return [
            'software_name','rollover_deposit','rollover','disable_rollover','withdrawal_limit','withdrawal_period',
            'withdrawal_auto_approve','withdrawal_auto_approve_max','min_deposit','max_deposit','min_withdrawal','max_withdrawal',
            'initial_bonus','cpa_baseline','cpa_value','revshare_reverse','allow_indexing','site_url',
        ];
    }

    private function normalizeFileUploadStateForForm(mixed $value): ?array
    {
        if (blank($value)) return null;
        if (is_array($value)) return array_values(array_filter($value));
        return is_string($value) ? [$value] : null;
    }

    private function normalizeUploadedFileForDatabase(mixed $value): ?string
    {
        if (blank($value)) return null;
        if (is_string($value)) return ltrim($value, '/');
        if (! is_array($value)) return null;
        $first = reset($value);
        if ($first instanceof TemporaryUploadedFile) {
            $path = \Helper::upload($first);
            return $path['path'] ?? null;
        }
        return is_string($first) ? ltrim($first, '/') : null;
    }

    public function imageUrl(?string $image): ?string
    {
        if (! filled($image)) return null;
        $image = ltrim((string) $image, '/');
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) return $image;
        if (str_starts_with($image, 'storage/')) return asset($image);
        return asset('storage/' . $image);
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}
