<?php



namespace App\Providers\Filament;

use App\Filament\Pages\DepositHistoryPage;
use App\Filament\Pages\DailyBonusHistoryPage;
use App\Filament\Pages\MissionCompletionHistoryPage;
use App\Filament\Pages\VipRedemptionHistoryPage;
use App\Filament\Pages\BetHistoryPage;
use App\Filament\Pages\AdminManagementPage;
use App\Filament\Pages\AdminPixKeysPage;
use App\Filament\Pages\AdminGameSessionsPage;
use App\Filament\Pages\AdminSystemToolsPage;
use App\Filament\Pages\AdminGameSyncPage;
use App\Filament\Pages\AdminProvidersPage;
use App\Filament\Pages\AdminGamesPage;
use App\Filament\Pages\AdminGameCategoriesPage;
use App\Filament\Pages\AdminVipsPage;
use App\Filament\Pages\AdminMissionsPage;
use App\Filament\Pages\AdminPromotionsPage;
use App\Filament\Pages\AdminCouponsPage;
use App\Filament\Pages\AdminKycVerificationsPage;
use App\Filament\Pages\AdminKycSettingsPage;
use App\Filament\Pages\AdminGameAggregatorPage;
use App\Filament\Pages\AdminPaymentGatewaysPage;
use App\Filament\Pages\AdminPlatformSettingsPage;
use App\Filament\Pages\AdminBetCrmPage;
use App\Filament\Pages\AdminSystemJobsPage;
use App\Filament\Pages\AdminBannersPage;
use App\Filament\Pages\AdminThemeColorsPage;
use App\Filament\Pages\AdminAffiliateWithdrawalsPage;
use App\Filament\Pages\AdminWithdrawalsPage;
use App\Filament\Pages\AdminAggregatorWalletsPage;
use App\Filament\Pages\AdminDailyBonusConfigPage;
use App\Filament\Pages\AdminDistributionSystemPage;
use App\Filament\Pages\AdminUsersPage;
use App\Filament\Pages\AdminUserInformationPage;
use App\Filament\Pages\AdminWalletsPage;
use App\Filament\Pages\AdminHomeSectionsPage;
use App\Filament\Pages\AdminRetroGamesPage;
use App\Filament\Pages\AdminRetroRoundsPage;
use App\Filament\Pages\AdminAuditLogsPage;
use App\Filament\Pages\CrmChartsPage;
use App\Filament\Pages\CrmDashboardPage;
use App\Filament\Pages\CrmClientsPage;
use App\Filament\Pages\CrmBettorsPage;
use App\Filament\Pages\CrmPlayFiverMetricsPage;
use App\Filament\Pages\CrmExportsPage;
use App\Filament\Pages\DashboardAdmin;
use App\Filament\Pages\GiveRoundsFreePage;
use App\Filament\Pages\LaravelLogsPage;
use App\Filament\Pages\LayoutCssCustom;
use App\Filament\Pages\LogsRoundsFreePage;
use App\Filament\Pages\ManageRoundsFreePage;
use App\Filament\Pages\RoundsFreePage;
use App\Filament\Pages\AffiliateManagementPage;
use App\Filament\Pages\AffiliateCpaPaidHistoryPage;
use App\Filament\Pages\InfluencerUsersPage;
use App\Filament\Pages\BettorManagementPage;
use App\Filament\Pages\GameSessionHistoryPage;
use App\Filament\Pages\Settings;
use App\Filament\Pages\AffiliateHistory;
use App\Filament\Pages\DetailsAffiliate;
use App\Filament\Resources\DepositResource;
use App\Http\Middleware\NoStoreAdminResponse;
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
use Illuminate\Support\HtmlString;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
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
                'primary' => Color::Orange,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->darkMode(true)
            ->font('Roboto Condensed')
            ->brandLogo(fn () => view('filament.components.logo'))
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages'
            )
            ->pages([
                DashboardAdmin::class,
                AdminHomeSectionsPage::class,
                AdminAuditLogsPage::class,
            ])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups(true)
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets'
            )
            ->widgets([])
            ->navigation(
                fn (NavigationBuilder $builder): NavigationBuilder => $builder->groups([
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
                ])
            )
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script>
                        (function () {
                            var el = document.documentElement;
                            el.classList.add('dark');
                            try { localStorage.setItem('theme', 'dark'); } catch (e) {}
                            new MutationObserver(function () {
                                if (! el.classList.contains('dark')) { el.classList.add('dark'); }
                            }).observe(el, { attributes: true, attributeFilter: ['class'] });
                        })();
                    </script>
                    HTML)
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(function_exists('asset') ? (function () {
                    $favicon = \App\Models\Setting::query()->value('software_favicon');

                    if (blank($favicon)) {
                        $url = asset('storage/icon/icon-padrao.webp');
                    } else {
                        $favicon = ltrim((string) $favicon, '/');

                        if (str_starts_with($favicon, 'http://') || str_starts_with($favicon, 'https://')) {
                            $url = $favicon;
                        } elseif (str_starts_with($favicon, 'storage/')) {
                            $url = asset($favicon);
                        } elseif (str_starts_with($favicon, 'uploads/')) {
                            $url = asset('storage/' . $favicon);
                        } else {
                            $url = asset('storage/' . $favicon);
                        }
                    }

                    $safeUrl = e($url);

                    return <<<HTML
                        <link rel="icon" href="{$safeUrl}" type="image/png">
                        <link rel="shortcut icon" href="{$safeUrl}" type="image/png">
                        <link rel="apple-touch-icon" href="{$safeUrl}">
                    HTML;
                })() : '')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <style>
                        /* ===================================================== */
                        /*  Tema do admin — FUNDO BLACK + paleta laranja          */
                        /*  (primária laranja vem do ->colors; modo escuro é       */
                        /*   forçado no HEAD_START; aqui é o fundo preto)         */
                        /* ===================================================== */

                        /* Modo escuro fixo: esconde o bot\u00e3o de trocar tema */
                        .fi-theme-switcher { display: none !important; }

                        /* Logo: tema escuro -> mostra o logo BRANCO, esconde o preto */
                        .show-in-light { display: none !important; }
                        .show-in-dark  { display: inline-block !important; }

                        /* ----- Fundo preto no chrome ----- */
                        .fi-body { background-color: #0a0a0a !important; }
                        .fi-sidebar {
                            background-color: #000000 !important;
                            border-right-color: rgba(249, 115, 22, 0.14) !important;
                        }
                        .fi-topbar, .fi-topbar > nav {
                            background-color: #0a0a0a !important;
                            border-bottom: 2px solid rgba(249, 115, 22, 0.22) !important;
                        }
                        .fi-main { background-color: #0a0a0a !important; }

                        /* ----- Tela de login (preto + laranja) ----- */
                        .fi-simple-layout {
                            background:
                                radial-gradient(900px 480px at 50% -8%, rgba(249, 115, 22, 0.20), transparent 60%),
                                #0a0a0a !important;
                        }
                        .fi-simple-main {
                            background-color: #141414 !important;
                            border-top: 4px solid #f97316 !important;
                            border-radius: 16px !important;
                            box-shadow:
                                0 24px 60px -22px rgba(249, 115, 22, 0.50),
                                0 8px 24px -14px rgba(0, 0, 0, 0.50) !important;
                        }
                    </style>
                    HTML)
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <div style="text-align:center;padding:14px 16px;font-size:12px;color:#a3a3a3;border-top:1px solid rgba(148,163,184,.14);line-height:1.7">
                        Sistema desenvolvido por <strong style="color:#e5e5e5">João (DEV)</strong>
                        &nbsp;•&nbsp; Telegram <a href="https://t.me/Joao_igaming" target="_blank" rel="noopener noreferrer" style="color:#fb923c;text-decoration:none">@Joao_igaming</a>
                        &nbsp;•&nbsp; <a href="https://t.me/central_igaming_channel" target="_blank" rel="noopener noreferrer" style="color:#fb923c;text-decoration:none">Canal</a>
                        &nbsp;•&nbsp; <a href="https://t.me/central_igaming" target="_blank" rel="noopener noreferrer" style="color:#fb923c;text-decoration:none">Grupo</a>
                        &nbsp;•&nbsp; <a href="https://www.youtube.com/@centraligaming" target="_blank" rel="noopener noreferrer" style="color:#fb923c;text-decoration:none">YouTube</a>
                    </div>
                    HTML)
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script>
                        (() => {
                            const KEY = 'filament_admin_sidebar_scroll';

                            function findScrollable(element) {
                                if (! element) {
                                    return null;
                                }

                                if (element.scrollHeight > element.clientHeight) {
                                    return element;
                                }

                                const children = element.querySelectorAll('*');

                                for (const child of children) {
                                    if (child.scrollHeight > child.clientHeight) {
                                        return child;
                                    }
                                }

                                return element;
                            }

                            function getSidebarScroller() {
                                const selectors = [
                                    '.fi-sidebar-nav',
                                    '.fi-sidebar nav',
                                    'aside.fi-sidebar',
                                    '.fi-sidebar',
                                    '[data-sidebar]',
                                ];

                                for (const selector of selectors) {
                                    const element = document.querySelector(selector);

                                    if (! element) {
                                        continue;
                                    }

                                    return findScrollable(element);
                                }

                                return null;
                            }

                            function saveSidebarScroll() {
                                const sidebar = getSidebarScroller();

                                if (! sidebar) {
                                    return;
                                }

                                sessionStorage.setItem(KEY, String(sidebar.scrollTop || 0));
                            }

                            function restoreSidebarScroll() {
                                const sidebar = getSidebarScroller();

                                if (! sidebar) {
                                    return;
                                }

                                const value = Number(sessionStorage.getItem(KEY) || 0);

                                requestAnimationFrame(() => {
                                    sidebar.scrollTop = value;
                                });

                                setTimeout(() => {
                                    sidebar.scrollTop = value;
                                }, 80);

                                setTimeout(() => {
                                    sidebar.scrollTop = value;
                                }, 250);
                            }

                            function bindSidebarScroll() {
                                const sidebar = getSidebarScroller();

                                if (! sidebar || sidebar.dataset.sidebarScrollMemory === '1') {
                                    return;
                                }

                                sidebar.dataset.sidebarScrollMemory = '1';

                                sidebar.addEventListener('scroll', saveSidebarScroll, {
                                    passive: true,
                                });
                            }

                            document.addEventListener('click', (event) => {
                                if (event.target.closest('.fi-sidebar a, aside a')) {
                                    saveSidebarScroll();
                                }
                            }, true);

                            window.addEventListener('beforeunload', saveSidebarScroll);

                            document.addEventListener('DOMContentLoaded', () => {
                                bindSidebarScroll();
                                restoreSidebarScroll();
                            });

                            document.addEventListener('livewire:navigated', () => {
                                bindSidebarScroll();
                                restoreSidebarScroll();
                            });

                            setTimeout(() => {
                                bindSidebarScroll();
                                restoreSidebarScroll();
                            }, 250);
                        })();
                    </script>
                HTML)
            )
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }



    private function crmGroup(): NavigationGroup
    {
        return NavigationGroup::make('ESTATÍSTICAS CRM')
            ->items([
                $this->pageItem('crm-dashboard', 'Gestao e Metricas', 'heroicon-o-chart-pie', CrmDashboardPage::getUrl(), ['filament.admin.pages.crm-dashboard-page']),
                $this->pageItem('crm-clients', 'Controle de apostadores', 'heroicon-o-users', CrmClientsPage::getUrl(), ['filament.admin.pages.crm-clients-page']),
                $this->pageItem('crm-charts', 'Métricas Graficas', 'heroicon-o-presentation-chart-bar', CrmChartsPage::getUrl(), ['filament.admin.pages.crm-charts-page']),
                $this->pageItem('crm-exports', 'Baixar dados', 'heroicon-o-arrow-down-tray', CrmExportsPage::getUrl(), ['filament.admin.pages.crm-exports-page']),
            ]);
    }

    private function dashboardGroup(): NavigationGroup
    {
        return NavigationGroup::make('Visão Geral')
            ->items([
                $this->pageItem(
                    key: 'dashboard',
                    label: 'Dashboard',
                    icon: 'heroicon-o-home',
                    url: DashboardAdmin::getUrl(),
                    activeRoutes: [
                        'filament.admin.pages.dashboard-admin',
                        'filament.pages.dashboard',
                    ],
                ),
            ]);
    }

    private function platformSettingsGroup(): NavigationGroup
    {
        return NavigationGroup::make('CONFIGURACOES DA PLATAFORMA')
            ->items([
                $this->pageItem('platform-settings', 'Configurações Primárias', 'heroicon-o-cog-6-tooth', AdminPlatformSettingsPage::getUrl(), ['filament.admin.pages.admin-platform-settings-page']),

                $this->pageItem('layout-css-custom', 'Configurações Segundarias', 'heroicon-o-paint-brush', LayoutCssCustom::getUrl(), ['filament.admin.pages.layout-css-custom', 'filament.pages.layout-css-custom']),
                $this->pageItem('admin-management', 'Configurações de adminstradores', 'heroicon-o-shield-check', AdminManagementPage::getUrl(), ['filament.admin.pages.admin-management-page', 'filament.pages.admin-management-page']),
                $this->pageItem('admin-distribution-system', 'Configurações de distribuicao', 'heroicon-o-scale', AdminDistributionSystemPage::getUrl(), ['filament.admin.pages.admin-distribution-system-page']),
                $this->pageItem('coupons', 'Configurações De Cupons', 'heroicon-o-ticket', AdminCouponsPage::getUrl(), ['filament.admin.pages.admin-coupons-page']),
            ]);
    }

    private function appearanceGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTAO DE LAYOUT')
            ->items([
                $this->pageItem('admin-theme-colors', 'Cores e tema', 'heroicon-o-swatch', AdminThemeColorsPage::getUrl(), ['filament.admin.pages.admin-theme-colors-page']),
                $this->pageItem('admin-banners', 'Banners da homepage', 'heroicon-o-photo', AdminBannersPage::getUrl(), ['filament.admin.pages.admin-banners-page']),
                $this->pageItem('promotions', 'Banners Campanhas', 'heroicon-o-megaphone', AdminPromotionsPage::getUrl(), ['filament.admin.pages.admin-promotions-page']),
            ]);
    }

    private function kycGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTAO DE KYC')
            ->items([
                $this->pageItem('kyc-verifications', 'Aprovação de contas', 'heroicon-o-shield-check', AdminKycVerificationsPage::getUrl(), ['filament.admin.pages.admin-kyc-verifications-page']),
                $this->pageItem('kyc-settings', 'Configuração', 'heroicon-o-identification', AdminKycSettingsPage::getUrl(), ['filament.admin.pages.admin-kyc-settings-page']),
            ]);
    }

    private function playFiverGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTAO PLAYFIVER')
            ->items([
                $this->pageItem('game-api-settings', 'Credencias Playfiver', 'heroicon-o-cpu-chip', AdminGameAggregatorPage::getUrl(), ['filament.admin.pages.admin-game-aggregator-page']),
                $this->pageItem('crm-playfiver', 'Métricas PlayFiver', 'heroicon-o-chart-bar-square', CrmPlayFiverMetricsPage::getUrl(), ['filament.admin.pages.crm-play-fiver-metrics-page']),
                $this->pageItem('admin-aggregator-wallets', 'Saldo PlayFiver', 'heroicon-o-banknotes', AdminAggregatorWalletsPage::getUrl(), ['filament.admin.pages.admin-aggregator-wallets-page']),
            ]);
    }

    private function gatewayGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTAO DE GATEWAYS')
            ->items([
                $this->pageItem('payment-settings', 'Credenciais de Gateways', 'heroicon-o-credit-card', AdminPaymentGatewaysPage::getUrl(), ['filament.admin.pages.admin-payment-gateways-page']),
            ]);
    }

    private function bettorsGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTAO DE APOSTADORES')
            ->items([
                $this->pageItem('admin-users', 'Apostadores', 'heroicon-o-users', AdminUsersPage::getUrl(), ['filament.admin.pages.admin-users-page', 'filament.admin.pages.admin-user-information-page']),
                $this->pageItem('admin-wallets', 'Carteiras', 'heroicon-o-wallet', AdminWalletsPage::getUrl(), ['filament.admin.pages.admin-wallets-page']),
                $this->pageItem('new-bettor-management', 'Métricas', 'heroicon-o-chart-pie', BettorManagementPage::getUrl(), ['filament.admin.pages.bettor-management-page']),
                $this->pageItem('admin-withdrawals', 'Retiradas', 'heroicon-o-banknotes', AdminWithdrawalsPage::getUrl(), ['filament.admin.pages.admin-withdrawals-page']),
                $this->pageItem('admin-pix-keys', 'Pix cadastrados', 'heroicon-o-key', AdminPixKeysPage::getUrl(), ['filament.admin.pages.admin-pix-keys-page']),
            ]);
    }

    private function bettorHistoriesGroup(): NavigationGroup
    {
        return NavigationGroup::make('HISTORICO DE APOSTADORES')
            ->items([
                $this->pageItem('history-deposits', 'Histórico de Depositos', 'heroicon-o-banknotes', DepositHistoryPage::getUrl(), ['filament.admin.pages.deposit-history-page']),
                $this->pageItem('history-bets', 'Histórico de Apostas', 'heroicon-o-ticket', BetHistoryPage::getUrl(), ['filament.admin.pages.bet-history-page']),
            ]);
    }

    private function affiliatesGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTAO DE AFILIADOS')
            ->items([
                $this->pageItem('new-affiliate-management', 'Afiliados', 'heroicon-o-user-group', AffiliateManagementPage::getUrl(), ['filament.admin.pages.affiliate-management-page']),
                $this->pageItem('admin-affiliate-withdrawals', 'Retiradas', 'heroicon-o-banknotes', AdminAffiliateWithdrawalsPage::getUrl(), ['filament.admin.pages.admin-affiliate-withdrawals-page']),
                $this->pageItem('new-affiliate-cpa-paid-history', 'Historico', 'heroicon-o-clock', AffiliateCpaPaidHistoryPage::getUrl(), ['filament.admin.pages.affiliate-cpa-paid-history-page']),
            ]);
    }

    private function influencerGroup(): NavigationGroup
    {
        return NavigationGroup::make('MODO INFLUECIADOR')
            ->items([
                $this->pageItem('new-influencer-users', 'Cadastrar influenciador', 'heroicon-o-megaphone', InfluencerUsersPage::getUrl(), ['filament.admin.pages.influencer-users-page']),
            ]);
    }

    private function gamesGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTAO DE JOGOS')
            ->items([
                $this->pageItem('game-providers', 'Provedores', 'heroicon-o-building-office-2', AdminProvidersPage::getUrl(), ['filament.admin.pages.admin-providers-page']),
                $this->pageItem('game-categories', 'Categorias', 'heroicon-o-squares-2x2', AdminGameCategoriesPage::getUrl(), ['filament.admin.pages.admin-game-categories-page']),
                $this->pageItem('games', 'Jogos', 'heroicon-o-puzzle-piece', AdminGamesPage::getUrl(), ['filament.admin.pages.admin-games-page']),
                $this->pageItem('home-sections', 'Secoes da Home', 'heroicon-o-rectangle-stack', url(config('app.filament_base_url', 'admin').'/secoes-da-home'), ['filament.admin.pages.secoes-da-home']),
                $this->pageItem('retro-games', 'Jogos Retrô', 'heroicon-o-command-line', AdminRetroGamesPage::getUrl(), ['filament.admin.pages.jogos-retro']),
                $this->pageItem('retro-rounds', 'Rodadas Retrô', 'heroicon-o-clock', AdminRetroRoundsPage::getUrl(), ['filament.admin.pages.rodadas-retro']),
            ]);
    }

    private function dailyRewardsGroup(): NavigationGroup
    {
        return NavigationGroup::make('RECOMPENSAS DIARIAS')
            ->items([
                $this->pageItem('missions', 'Missoes', 'heroicon-o-flag', AdminMissionsPage::getUrl(), ['filament.admin.pages.admin-missions-page']),
                $this->pageItem('vips', 'Cadastro VIP', 'heroicon-o-sparkles', AdminVipsPage::getUrl(), ['filament.admin.pages.admin-vips-page']),
                $this->pageItem('admin-daily-bonus-config', 'Raspadinha', 'heroicon-o-gift', AdminDailyBonusConfigPage::getUrl(), ['filament.admin.pages.admin-daily-bonus-config-page']),
            ]);
    }

    private function rewardHistoriesGroup(): NavigationGroup
    {
        return NavigationGroup::make('HISTORICO DE  RECOMPENSAS')
            ->items([
                $this->pageItem('history-daily-bonus', 'Histórico Raspadinha', 'heroicon-o-gift', DailyBonusHistoryPage::getUrl(), ['filament.admin.pages.daily-bonus-history-page']),
                $this->pageItem('history-missions', 'Histórico Missões', 'heroicon-o-flag', MissionCompletionHistoryPage::getUrl(), ['filament.admin.pages.mission-completion-history-page']),
                $this->pageItem('history-vip', 'Histórico VIP', 'heroicon-o-sparkles', VipRedemptionHistoryPage::getUrl(), ['filament.admin.pages.vip-redemption-history-page']),
            ]);
    }

    private function freeRoundsGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTAO DE RODADAS GRATIS')
            ->items([
                $this->pageItem('free-rounds-config', 'Configuracao De Rodadas', 'heroicon-o-adjustments-horizontal', RoundsFreePage::getUrl(), ['filament.admin.pages.rounds-free', 'filament.pages.rounds-free']),
                $this->pageItem('free-rounds-manage', 'Gestao De rodadas', 'heroicon-o-list-bullet', ManageRoundsFreePage::getUrl(), ['filament.admin.pages.manage-rounds-free-page', 'filament.pages.manage-rounds-free-page']),
                $this->pageItem('free-rounds-give', 'Enviar rodadas', 'heroicon-o-gift-top', GiveRoundsFreePage::getUrl(), ['filament.admin.pages.give-rounds-free-page', 'filament.pages.give-rounds-free-page']),
                $this->pageItem('free-rounds-history', 'Histórico Rodadas', 'heroicon-o-clock', LogsRoundsFreePage::getUrl(), ['filament.admin.pages.logs-rounds-free', 'filament.pages.logs-rounds-free']),
            ]);
    }

    private function systemGroup(): NavigationGroup
    {
        return NavigationGroup::make('GESTAO DE SISTEMA')
            ->items([
                $this->pageItem('system-tools', 'Manutencao Do sistema', 'heroicon-o-wrench-screwdriver', AdminSystemToolsPage::getUrl(), ['filament.admin.pages.admin-system-tools-page']),
                $this->pageItem('system-jobs', 'Tarefas automáticas', 'heroicon-o-cpu-chip', AdminSystemJobsPage::getUrl(), ['filament.admin.pages.admin-system-jobs-page']),
                $this->pageItem('laravel-logs', 'Logs Do sistema', 'heroicon-o-document-magnifying-glass', LaravelLogsPage::getUrl(), ['filament.admin.pages.laravel-logs-page', 'filament.pages.laravel-logs-page']),
                $this->pageItem('sync-games', 'Sincronizar Jogos', 'heroicon-o-arrow-path', AdminGameSyncPage::getUrl(), ['filament.admin.pages.admin-game-sync-page']),
                $this->pageItem('audit-logs', 'Auditoria de Acoes', 'heroicon-o-shield-check', url(config('app.filament_base_url', 'admin').'/auditoria-acoes'), ['filament.admin.pages.auditoria-acoes']),
            ]);
    }

    private function pageItem(
        string $key,
        string $label,
        string $icon,
        string $url,
        array $activeRoutes = [],
    ): NavigationItem {
        return NavigationItem::make($key)
            ->label($label)
            ->icon($icon)
            ->url($url)
            ->isActiveWhen(fn (): bool => $this->routeMatches($activeRoutes));
    }

    private function routeMatches(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}