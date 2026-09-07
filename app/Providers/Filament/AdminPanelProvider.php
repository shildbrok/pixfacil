<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AffiliateCpaPaidHistoryPage;
use App\Filament\Pages\AffiliateManagementPage;
use App\Filament\Pages\AdminAffiliateWithdrawalsPage;
use App\Filament\Pages\AdminAggregatorWalletsPage;
use App\Filament\Pages\AdminAuditLogsPage;
use App\Filament\Pages\AdminBannersPage;
use App\Filament\Pages\AdminCouponsPage;
use App\Filament\Pages\AdminDailyBonusConfigPage;
use App\Filament\Pages\AdminDistributionSystemPage;
use App\Filament\Pages\AdminFrontendContentPage;
use App\Filament\Pages\AdminGameAggregatorPage;
use App\Filament\Pages\AdminGameCategoriesPage;
use App\Filament\Pages\AdminGamesPage;
use App\Filament\Pages\AdminGameSessionsPage;
use App\Filament\Pages\AdminGameSyncPage;
use App\Filament\Pages\AdminHomeSectionsPage;
use App\Filament\Pages\AdminKycSettingsPage;
use App\Filament\Pages\AdminKycVerificationsPage;
use App\Filament\Pages\AdminManagementPage;
use App\Filament\Pages\AdminMissionsPage;
use App\Filament\Pages\AdminPaymentGatewaysPage;
use App\Filament\Pages\AdminPixKeysPage;
use App\Filament\Pages\AdminPlatformSettingsPage;
use App\Filament\Pages\AdminPromotionsPage;
use App\Filament\Pages\AdminProvidersPage;
use App\Filament\Pages\AdminRetroGamesPage;
use App\Filament\Pages\AdminRetroRoundsPage;
use App\Filament\Pages\AdminSystemJobsPage;
use App\Filament\Pages\AdminSystemToolsPage;
use App\Filament\Pages\AdminThemeColorsPage;
use App\Filament\Pages\AdminUserInformationPage;
use App\Filament\Pages\AdminUsersPage;
use App\Filament\Pages\AdminVipsPage;
use App\Filament\Pages\AdminWalletsPage;
use App\Filament\Pages\AdminWithdrawalsPage;
use App\Filament\Pages\BetHistoryPage;
use App\Filament\Pages\BettorManagementPage;
use App\Filament\Pages\CrmBettorsPage;
use App\Filament\Pages\CrmChartsPage;
use App\Filament\Pages\CrmClientsPage;
use App\Filament\Pages\CrmDashboardPage;
use App\Filament\Pages\CrmExportsPage;
use App\Filament\Pages\CrmPlayFiverMetricsPage;
use App\Filament\Pages\DailyBonusHistoryPage;
use App\Filament\Pages\DashboardAdmin;
use App\Filament\Pages\DepositHistoryPage;
use App\Filament\Pages\GiveRoundsFreePage;
use App\Filament\Pages\InfluencerUsersPage;
use App\Filament\Pages\LaravelLogsPage;
use App\Filament\Pages\LayoutCssCustom;
use App\Filament\Pages\LogsRoundsFreePage;
use App\Filament\Pages\ManageRoundsFreePage;
use App\Filament\Pages\MissionCompletionHistoryPage;
use App\Filament\Pages\RoundsFreePage;
use App\Filament\Pages\VipRedemptionHistoryPage;
use App\Http\Middleware\NoStoreAdminResponse;
use App\Models\Setting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canView(): bool
    {
        return static::canAccess();
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('app.filament_base_url', 'admin'))
            ->login()
            ->colors([
                'danger' => Color::Red,
                'gray' => Color::Neutral,
                'info' => Color::Blue,
                'primary' => Color::Emerald,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->darkMode(true)
            ->font('Roboto Condensed')
            ->brandLogo(fn () => view('filament.components.logo'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                DashboardAdmin::class,
                AdminHomeSectionsPage::class,
                AdminAuditLogsPage::class,
            ])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups(true)
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $builder->groups([
                $this->dashboardGroup(),
                $this->crmGroup(),
                $this->platformSettingsGroup(),
                $this->appearanceGroup(),
                $this->kycGroup(),
                $this->playFiverGroup(),
                $this->gatewayGroup(),
                $this->bettorsGroup(),
                $this->bettorHistoriesGroup(),
                $this->affiliatesGroup(),
                $this->influencerGroup(),
                $this->gamesGroup(),
                $this->dailyRewardsGroup(),
                $this->rewardHistoriesGroup(),
                $this->freeRoundsGroup(),
                $this->systemGroup(),
            ]))
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): HtmlString => new HtmlString(<<<'HTML'
<script>
(function(){var el=document.documentElement;el.classList.add('dark');try{localStorage.setItem('theme','dark')}catch(e){}new MutationObserver(function(){if(!el.classList.contains('dark'))el.classList.add('dark')}).observe(el,{attributes:true,attributeFilter:['class']})})();
</script>
HTML))
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => new HtmlString($this->adminHeadHtml()))
            ->renderHook(PanelsRenderHook::FOOTER, fn (): HtmlString => new HtmlString($this->adminFooterHtml()))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): HtmlString => new HtmlString(<<<'HTML'
<script>
(()=>{const KEY='filament_admin_sidebar_scroll';function scroller(){for(const s of ['.fi-sidebar-nav','.fi-sidebar nav','aside.fi-sidebar','.fi-sidebar']){const e=document.querySelector(s);if(e)return e}return null}function save(){const e=scroller();if(e)sessionStorage.setItem(KEY,String(e.scrollTop||0))}function restore(){const e=scroller();if(!e)return;const v=Number(sessionStorage.getItem(KEY)||0);requestAnimationFrame(()=>e.scrollTop=v);setTimeout(()=>e.scrollTop=v,100)}function bind(){const e=scroller();if(!e||e.dataset.scrollMemory)return;e.dataset.scrollMemory='1';e.addEventListener('scroll',save,{passive:true})}document.addEventListener('click',e=>{if(e.target.closest('.fi-sidebar a,aside a'))save()},true);window.addEventListener('beforeunload',save);document.addEventListener('DOMContentLoaded',()=>{bind();restore()});document.addEventListener('livewire:navigated',()=>{bind();restore()});setTimeout(()=>{bind();restore()},250)})();
</script>
HTML))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                NoStoreAdminResponse::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }

    private function adminHeadHtml(): string
    {
        $setting = Setting::query()->first();
        $favicon = $this->assetUrl($setting?->software_favicon) ?: asset('storage/icon/icon-padrao.webp');
        $favicon = e($favicon);

        return <<<HTML
<link rel="icon" href="{$favicon}" type="image/png">
<link rel="shortcut icon" href="{$favicon}" type="image/png">
<link rel="apple-touch-icon" href="{$favicon}">
<style>
.fi-theme-switcher{display:none!important}.show-in-light{display:none!important}.show-in-dark{display:inline-block!important}
.fi-body,.fi-main{background:#050706!important}.fi-sidebar{background:#020403!important;border-right-color:rgba(57,242,92,.14)!important}.fi-topbar,.fi-topbar>nav{background:#050706!important;border-bottom:1px solid rgba(57,242,92,.18)!important}
.fi-simple-layout{background:radial-gradient(900px 480px at 50% -8%,rgba(57,242,92,.15),transparent 60%),#050706!important}.fi-simple-main{background:#0a0d0b!important;border-top:3px solid #39f25c!important;border-radius:16px!important;box-shadow:0 24px 60px -22px rgba(57,242,92,.28),0 8px 24px -14px rgba(0,0,0,.55)!important}
.fi-sidebar-item.fi-active>a,.fi-sidebar-item-button[aria-current="page"]{background:rgba(57,242,92,.09)!important;color:#57f372!important}.fi-sidebar-group-label{letter-spacing:.08em}
</style>
HTML;
    }

    private function adminFooterHtml(): string
    {
        $name = e(Setting::query()->value('software_name') ?: config('app.name', 'Plataforma'));
        return '<div style="text-align:center;padding:14px 16px;font-size:12px;color:#8f9892;border-top:1px solid rgba(57,242,92,.12);line-height:1.7">Painel administrativo <strong style="color:#dfe8e1">'.$name.'</strong> • Configurações e ações sensíveis são registradas na auditoria.</div>';
    }

    private function assetUrl(?string $value): ?string
    {
        if (blank($value)) return null;
        $value = ltrim((string) $value, '/');
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) return $value;
        if (str_starts_with($value, 'storage/')) return asset($value);
        return asset('storage/' . $value);
    }

    private function dashboardGroup(): NavigationGroup
    {
        return NavigationGroup::make('VISÃO GERAL')->items([
            $this->pageItem('dashboard','Dashboard','heroicon-o-home',DashboardAdmin::getUrl(),['filament.admin.pages.dashboard-admin','filament.pages.dashboard']),
        ]);
    }

    private function crmGroup(): NavigationGroup
    {
        return NavigationGroup::make('ESTATÍSTICAS CRM')->items([
            $this->pageItem('crm-dashboard','Gestão e Métricas','heroicon-o-chart-pie',CrmDashboardPage::getUrl(),['filament.admin.pages.crm-dashboard-page']),
            $this->pageItem('crm-clients','Controle de apostadores','heroicon-o-users',CrmClientsPage::getUrl(),['filament.admin.pages.crm-clients-page']),
            $this->pageItem('crm-charts','Métricas gráficas','heroicon-o-presentation-chart-bar',CrmChartsPage::getUrl(),['filament.admin.pages.crm-charts-page']),
            $this->pageItem('crm-exports','Baixar dados','heroicon-o-arrow-down-tray',CrmExportsPage::getUrl(),['filament.admin.pages.crm-exports-page']),
        ]);
    }

    private function platformSettingsGroup(): NavigationGroup
    {
        return NavigationGroup::make('CONFIGURAÇÕES DA PLATAFORMA')->items([
            $this->pageItem('platform-settings','Configurações Primárias','heroicon-o-cog-6-tooth',AdminPlatformSettingsPage::getUrl(),['filament.admin.pages.admin-platform-settings-page']),
            $this->pageItem('layout-css-custom','Configurações Secundárias','heroicon-o-paint-brush',LayoutCssCustom::getUrl(),['filament.admin.pages.layout-css-custom','filament.pages.layout-css-custom']),
            $this->pageItem('admin-management','Administradores','heroicon-o-shield-check',AdminManagementPage::getUrl(),['filament.admin.pages.admin-management-page','filament.pages.admin-management-page']),
            $this->pageItem('admin-distribution-system','Distribuição','heroicon-o-scale',AdminDistributionSystemPage::getUrl(),['filament.admin.pages.admin-distribution-system-page']),
            $this->pageItem('coupons','Cupons','heroicon-o-ticket',AdminCouponsPage::getUrl(),['filament.admin.pages.admin-coupons-page']),
        ]);
    }

    private function appearanceGroup(): NavigationGroup
    {
        return NavigationGroup::make('TEMA E APARÊNCIA')->items([
            $this->pageItem('admin-theme-colors','Cores e tema','heroicon-o-swatch',AdminThemeColorsPage::getUrl(),['filament.admin.pages.admin-theme-colors-page']),
            $this->pageItem('frontend-content','Conteúdo das Telas','heroicon-o-pencil-square',AdminFrontendContentPage::getUrl(),['filament.admin.pages.admin-frontend-content-page']),
            $this->pageItem('admin-banners','Banners da Plataforma','heroicon-o-photo',AdminBannersPage::getUrl(),['filament.admin.pages.admin-banners-page']),
            $this->pageItem('promotions','Campanhas Promocionais','heroicon-o-megaphone',AdminPromotionsPage::getUrl(),['filament.admin.pages.admin-promotions-page']),
        ]);
    }

    private function kycGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTÃO DE KYC')->items([
            $this->pageItem('kyc-verifications','Aprovação de contas','heroicon-o-shield-check',AdminKycVerificationsPage::getUrl(),['filament.admin.pages.admin-kyc-verifications-page']),
            $this->pageItem('kyc-settings','Configuração','heroicon-o-identification',AdminKycSettingsPage::getUrl(),['filament.admin.pages.admin-kyc-settings-page']),
        ]);
    }

    private function playFiverGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTÃO PLAYFIVER')->items([
            $this->pageItem('game-api-settings','Credenciais PlayFiver','heroicon-o-cpu-chip',AdminGameAggregatorPage::getUrl(),['filament.admin.pages.admin-game-aggregator-page']),
            $this->pageItem('crm-playfiver','Métricas PlayFiver','heroicon-o-chart-bar-square',CrmPlayFiverMetricsPage::getUrl(),['filament.admin.pages.crm-play-fiver-metrics-page']),
            $this->pageItem('admin-aggregator-wallets','Saldo PlayFiver','heroicon-o-banknotes',AdminAggregatorWalletsPage::getUrl(),['filament.admin.pages.admin-aggregator-wallets-page']),
        ]);
    }

    private function gatewayGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTÃO DE GATEWAYS')->items([
            $this->pageItem('payment-settings','Credenciais de Gateways','heroicon-o-credit-card',AdminPaymentGatewaysPage::getUrl(),['filament.admin.pages.admin-payment-gateways-page']),
        ]);
    }

    private function bettorsGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTÃO DE APOSTADORES')->items([
            $this->pageItem('admin-users','Apostadores','heroicon-o-users',AdminUsersPage::getUrl(),['filament.admin.pages.admin-users-page','filament.admin.pages.admin-user-information-page']),
            $this->pageItem('admin-wallets','Carteiras','heroicon-o-wallet',AdminWalletsPage::getUrl(),['filament.admin.pages.admin-wallets-page']),
            $this->pageItem('new-bettor-management','Métricas','heroicon-o-chart-pie',BettorManagementPage::getUrl(),['filament.admin.pages.bettor-management-page']),
            $this->pageItem('admin-withdrawals','Retiradas','heroicon-o-banknotes',AdminWithdrawalsPage::getUrl(),['filament.admin.pages.admin-withdrawals-page']),
            $this->pageItem('admin-pix-keys','PIX cadastrados','heroicon-o-key',AdminPixKeysPage::getUrl(),['filament.admin.pages.admin-pix-keys-page']),
        ]);
    }

    private function bettorHistoriesGroup(): NavigationGroup
    {
        return NavigationGroup::make('HISTÓRICO DE APOSTADORES')->items([
            $this->pageItem('history-deposits','Histórico de Depósitos','heroicon-o-banknotes',DepositHistoryPage::getUrl(),['filament.admin.pages.deposit-history-page']),
            $this->pageItem('history-bets','Histórico de Apostas','heroicon-o-ticket',BetHistoryPage::getUrl(),['filament.admin.pages.bet-history-page']),
        ]);
    }

    private function affiliatesGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTÃO DE AFILIADOS')->items([
            $this->pageItem('new-affiliate-management','Afiliados','heroicon-o-user-group',AffiliateManagementPage::getUrl(),['filament.admin.pages.affiliate-management-page']),
            $this->pageItem('admin-affiliate-withdrawals','Retiradas','heroicon-o-banknotes',AdminAffiliateWithdrawalsPage::getUrl(),['filament.admin.pages.admin-affiliate-withdrawals-page']),
            $this->pageItem('new-affiliate-cpa-paid-history','Histórico','heroicon-o-clock',AffiliateCpaPaidHistoryPage::getUrl(),['filament.admin.pages.affiliate-cpa-paid-history-page']),
        ]);
    }

    private function influencerGroup(): NavigationGroup
    {
        return NavigationGroup::make('MODO INFLUENCIADOR')->items([
            $this->pageItem('new-influencer-users','Cadastrar influenciador','heroicon-o-megaphone',InfluencerUsersPage::getUrl(),['filament.admin.pages.influencer-users-page']),
        ]);
    }

    private function gamesGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTÃO DE JOGOS')->items([
            $this->pageItem('game-providers','Provedores','heroicon-o-building-office-2',AdminProvidersPage::getUrl(),['filament.admin.pages.admin-providers-page']),
            $this->pageItem('game-categories','Categorias','heroicon-o-squares-2x2',AdminGameCategoriesPage::getUrl(),['filament.admin.pages.admin-game-categories-page']),
            $this->pageItem('games','Jogos','heroicon-o-puzzle-piece',AdminGamesPage::getUrl(),['filament.admin.pages.admin-games-page']),
            $this->pageItem('home-sections','Seções da Home','heroicon-o-rectangle-stack',url(config('app.filament_base_url','admin').'/secoes-da-home'),['filament.admin.pages.secoes-da-home']),
            $this->pageItem('retro-games','Jogos Retrô','heroicon-o-command-line',AdminRetroGamesPage::getUrl(),['filament.admin.pages.jogos-retro']),
            $this->pageItem('retro-rounds','Rodadas Retrô','heroicon-o-clock',AdminRetroRoundsPage::getUrl(),['filament.admin.pages.rodadas-retro']),
        ]);
    }

    private function dailyRewardsGroup(): NavigationGroup
    {
        return NavigationGroup::make('RECOMPENSAS DIÁRIAS')->items([
            $this->pageItem('missions','Missões','heroicon-o-flag',AdminMissionsPage::getUrl(),['filament.admin.pages.admin-missions-page']),
            $this->pageItem('vips','Cadastro VIP','heroicon-o-sparkles',AdminVipsPage::getUrl(),['filament.admin.pages.admin-vips-page']),
            $this->pageItem('admin-daily-bonus-config','Raspadinha','heroicon-o-gift',AdminDailyBonusConfigPage::getUrl(),['filament.admin.pages.admin-daily-bonus-config-page']),
        ]);
    }

    private function rewardHistoriesGroup(): NavigationGroup
    {
        return NavigationGroup::make('HISTÓRICO DE RECOMPENSAS')->items([
            $this->pageItem('history-daily-bonus','Histórico Raspadinha','heroicon-o-gift',DailyBonusHistoryPage::getUrl(),['filament.admin.pages.daily-bonus-history-page']),
            $this->pageItem('history-missions','Histórico Missões','heroicon-o-flag',MissionCompletionHistoryPage::getUrl(),['filament.admin.pages.mission-completion-history-page']),
            $this->pageItem('history-vip','Histórico VIP','heroicon-o-sparkles',VipRedemptionHistoryPage::getUrl(),['filament.admin.pages.vip-redemption-history-page']),
        ]);
    }

    private function freeRoundsGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTÃO DE RODADAS GRÁTIS')->items([
            $this->pageItem('free-rounds-config','Configuração de Rodadas','heroicon-o-adjustments-horizontal',RoundsFreePage::getUrl(),['filament.admin.pages.rounds-free','filament.pages.rounds-free']),
            $this->pageItem('free-rounds-manage','Gestão de Rodadas','heroicon-o-list-bullet',ManageRoundsFreePage::getUrl(),['filament.admin.pages.manage-rounds-free-page','filament.pages.manage-rounds-free-page']),
            $this->pageItem('free-rounds-give','Enviar rodadas','heroicon-o-gift-top',GiveRoundsFreePage::getUrl(),['filament.admin.pages.give-rounds-free-page','filament.pages.give-rounds-free-page']),
            $this->pageItem('free-rounds-history','Histórico Rodadas','heroicon-o-clock',LogsRoundsFreePage::getUrl(),['filament.admin.pages.logs-rounds-free','filament.pages.logs-rounds-free']),
        ]);
    }

    private function systemGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTÃO DE SISTEMA')->items([
            $this->pageItem('system-tools','Manutenção do sistema','heroicon-o-wrench-screwdriver',AdminSystemToolsPage::getUrl(),['filament.admin.pages.admin-system-tools-page']),
            $this->pageItem('system-jobs','Tarefas automáticas','heroicon-o-cpu-chip',AdminSystemJobsPage::getUrl(),['filament.admin.pages.admin-system-jobs-page']),
            $this->pageItem('laravel-logs','Logs do sistema','heroicon-o-document-magnifying-glass',LaravelLogsPage::getUrl(),['filament.admin.pages.laravel-logs-page','filament.pages.laravel-logs-page']),
            $this->pageItem('sync-games','Sincronizar Jogos','heroicon-o-arrow-path',AdminGameSyncPage::getUrl(),['filament.admin.pages.admin-game-sync-page']),
            $this->pageItem('audit-logs','Auditoria de Ações','heroicon-o-shield-check',url(config('app.filament_base_url','admin').'/auditoria-acoes'),['filament.admin.pages.auditoria-acoes']),
        ]);
    }

    private function pageItem(string $key,string $label,string $icon,string $url,array $activeRoutes=[]): NavigationItem
    {
        return NavigationItem::make($key)->label($label)->icon($icon)->url($url)->isActiveWhen(fn (): bool => $this->routeMatches($activeRoutes));
    }

    private function routeMatches(array $patterns): bool
    {
        foreach ($patterns as $pattern) if (request()->routeIs($pattern)) return true;
        return false;
    }
}
