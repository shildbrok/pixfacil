<?php



namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class CrmDashboardPage extends Page
{
    protected static string $view = 'filament.pages.crm-dashboard-page';

    protected static ?string $title = 'Dashboard CRM';
    protected static ?string $navigationLabel = 'Dashboard CRM';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'crm-dashboard';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function stats(): array
    {
        // ~32 agregações num dashboard: cacheado 15s (continua "ao vivo") para não
        // martelar o banco a cada render/interação. TTL curto = sem invalidação manual.
        return cache()->remember('admin:crm-dashboard:stats:v1', 15, function (): array {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $totalDeposits = (float) DB::table('deposits')->where('status', 1)->sum('amount');
        $totalDepositCount = DB::table('deposits')->where('status', 1)->count();

        $totalWithdrawals = (float) DB::table('withdrawals')->sum('amount');
        $totalWithdrawalCount = DB::table('withdrawals')->count();

        $depositsToday = (float) DB::table('deposits')->where('status', 1)->whereDate('created_at', $today)->sum('amount');
        $depositsYesterday = (float) DB::table('deposits')->where('status', 1)->whereDate('created_at', $yesterday)->sum('amount');

        $withdrawalsToday = (float) DB::table('withdrawals')->whereDate('created_at', $today)->sum('amount');
        $withdrawalsYesterday = (float) DB::table('withdrawals')->whereDate('created_at', $yesterday)->sum('amount');

        $betsToday = (float) DB::table('orders')->where('type', 'bet')->whereDate('created_at', $today)->sum('amount');
        $winsToday = (float) DB::table('orders')->where('type', 'win')->whereDate('created_at', $today)->sum('amount');

        $usersTotal = User::query()->count();
        $newToday = User::query()->whereDate('created_at', $today)->count();

        $newTodayDepositors = User::query()
            ->whereDate('users.created_at', $today)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('deposits')
                    ->whereColumn('deposits.user_id', 'users.id')
                    ->where('deposits.status', 1);
            })
            ->count();

        $depositorsToday = DB::table('deposits')
            ->where('status', 1)
            ->whereDate('created_at', $today)
            ->distinct('user_id')
            ->count('user_id');

        $depositCountToday = DB::table('deposits')
            ->where('status', 1)
            ->whereDate('created_at', $today)
            ->count();

        $playerBalance = (float) DB::table('wallets')
            ->where('active', 1)
            ->sum(DB::raw('coalesce(balance,0) + coalesce(balance_bonus,0) + coalesce(balance_withdrawal,0)'));

        $playFiver = $this->playFiverWalletStats();

        $affiliatePayable = (float) DB::table('wallets')->sum('refer_rewards');

        $playersNow = $this->playersOnlineNow();

        return [
            'total_deposits' => $totalDeposits,
            'total_deposit_count' => $totalDepositCount,
            'total_withdrawals' => $totalWithdrawals,
            'total_withdrawal_count' => $totalWithdrawalCount,
            'players_now' => $playersNow,
            'bets_today' => $betsToday,
            'wins_today' => $winsToday,
            'house_profit_today' => $betsToday - $winsToday,
            'users_total' => $usersTotal,
            'new_today' => $newToday,
            'deposits_today' => $depositsToday,
            'deposits_yesterday' => $depositsYesterday,
            'deposits_vs_yesterday' => $this->variationLabel($depositsToday, $depositsYesterday),
            'withdrawals_today' => $withdrawalsToday,
            'withdrawals_yesterday' => $withdrawalsYesterday,
            'withdrawals_vs_yesterday' => $this->variationLabel($withdrawalsToday, $withdrawalsYesterday),
            'net_flow_today' => $depositsToday - $withdrawalsToday,
            'net_flow_yesterday' => $depositsYesterday - $withdrawalsYesterday,
            'net_flow_vs_yesterday' => $this->variationLabel($depositsToday - $withdrawalsToday, $depositsYesterday - $withdrawalsYesterday),
            'player_balance' => $playerBalance,
            'playfiver_total' => $playFiver['total'],
            'playfiver_count' => $playFiver['count'],
            'playfiver_operational' => $playFiver['operational'],
            'playfiver_info' => $playFiver['info'],
            'playfiver_primary' => $playFiver['primary'],
            'affiliate_payable' => $affiliatePayable,
            'new_conversion' => $newToday > 0 ? ($newTodayDepositors / $newToday) * 100 : 0,
            'new_conversion_label' => $newTodayDepositors . ' depositantes / ' . $newToday . ' novos',
            'avg_ticket_today' => $depositorsToday > 0 ? $depositsToday / $depositorsToday : 0,
            'deposit_count_today' => $depositCountToday,
        ];
        });
    }

    public function depositDistribution(): array
    {
        $rows = DB::table('users')
            ->leftJoin('deposits', function ($join) {
                $join->on('deposits.user_id', '=', 'users.id')
                    ->where('deposits.status', '=', 1);
            })
            ->select('users.id')
            ->selectRaw('count(deposits.id) as deposits_count')
            ->groupBy('users.id')
            ->get();

        return [
            '1 depósito' => $rows->where('deposits_count', 1)->count(),
            '2 depósitos' => $rows->where('deposits_count', 2)->count(),
            '3 depósitos' => $rows->where('deposits_count', 3)->count(),
            '4+ depósitos' => $rows->filter(fn ($row) => (int) $row->deposits_count >= 4)->count(),
        ];
    }

    public function providerResume(): array
    {
        return DB::table('orders')
            ->leftJoin('games', function ($join) {
                $join->on('games.game_code', '=', 'orders.game')
                    ->orOn('games.game_id', '=', 'orders.game')
                    ->orOn('games.game_code', '=', 'orders.game_uuid')
                    ->orOn('games.game_id', '=', 'orders.game_uuid');
            })
            ->leftJoin('providers', 'providers.id', '=', 'games.provider_id')
            ->selectRaw("coalesce(providers.code, providers.name, 'sem_provedor') as provider_code")
            ->selectRaw("coalesce(providers.name, providers.code, 'Sem provedor') as provider_name")
            ->selectRaw("coalesce(orders.providers, 'sem_agregador') as aggregator")
            ->selectRaw("sum(case when orders.type = 'bet' then orders.amount else 0 end) as total_bet")
            ->selectRaw("sum(case when orders.type = 'win' then orders.amount else 0 end) as total_win")
            ->selectRaw("sum(case when orders.type = 'bet' then orders.amount else 0 end) - sum(case when orders.type = 'win' then orders.amount else 0 end) as house_result")
            ->selectRaw("count(*) as events")
            ->groupBy('provider_code', 'provider_name', 'aggregator')
            ->orderByDesc('house_result')
            ->limit(8)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    public function recentFlow(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        return $days->map(function (string $date): array {
            $deposits = (float) DB::table('deposits')->where('status', 1)->whereDate('created_at', $date)->sum('amount');
            $withdrawals = (float) DB::table('withdrawals')->whereDate('created_at', $date)->sum('amount');
            $bets = (float) DB::table('orders')->where('type', 'bet')->whereDate('created_at', $date)->sum('amount');
            $wins = (float) DB::table('orders')->where('type', 'win')->whereDate('created_at', $date)->sum('amount');

            return [
                'date' => \Carbon\Carbon::parse($date)->format('d/m'),
                'deposits' => $deposits,
                'withdrawals' => $withdrawals,
                'bets' => $bets,
                'wins' => $wins,
                'house' => $bets - $wins,
            ];
        })->toArray();
    }

    private function playersOnlineNow(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('game_sessions')) {
            return DB::table('orders')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->distinct('user_id')
                ->count('user_id');
        }

        return DB::table('game_sessions')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('last_ping_at', '>=', now()->subMinutes(5))
                    ->orWhere('started_at', '>=', now()->subMinutes(5))
                    ->orWhere('updated_at', '>=', now()->subMinutes(5));
            })
            ->distinct('user_id')
            ->count('user_id');
    }

    private function playFiverWalletStats(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('aggregator_wallets')) {
            return [
                'total' => 0,
                'count' => 0,
                'operational' => 0,
                'info' => 0,
                'primary' => 0,
            ];
        }

        $query = DB::table('aggregator_wallets')->where('provider', 'playfiver');

        return [
            'total' => (float) (clone $query)->sum('balance'),
            'count' => (clone $query)->count(),
            'operational' => (float) (clone $query)->where('info_only', 0)->sum('balance'),
            'info' => (float) (clone $query)->where('info_only', 1)->sum('balance'),
            'primary' => (float) (clone $query)->where('is_primary', 1)->sum('balance'),
        ];
    }

    private function variationLabel(float $current, float $previous): string
    {
        if (abs($previous) < 0.00001) {
            return 'n/d';
        }

        $variation = (($current - $previous) / abs($previous)) * 100;
        $arrow = $variation >= 0 ? '↑' : '↓';

        return $arrow . ' ' . number_format(abs($variation), 2, ',', '.') . '%';
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    public function pct($value): string
    {
        return number_format((float) $value, 2, ',', '.') . '%';
    }

    public function barWidth($value, $max): int
    {
        $max = max((float) $max, 1);
        return max(4, min(100, (int) round(((float) $value / $max) * 100)));
    }
}
