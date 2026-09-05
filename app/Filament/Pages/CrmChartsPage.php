<?php



namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class CrmChartsPage extends Page
{
    protected static string $view = 'filament.pages.crm-charts-page';

    protected static ?string $title = 'Gráficos CRM';
    protected static ?string $navigationLabel = 'Gráficos e Métricas';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'crm-graficos';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function dailySeries(): array
    {
        return collect(range(13, 0))->map(function (int $i): array {
            $date = now()->subDays($i)->toDateString();

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

    public function providerSeries(): array
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
            ->orderByDesc('total_bet')
            ->limit(12)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
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
            '0 depósitos' => $rows->where('deposits_count', 0)->count(),
            '1 depósito' => $rows->where('deposits_count', 1)->count(),
            '2 depósitos' => $rows->where('deposits_count', 2)->count(),
            '3 depósitos' => $rows->where('deposits_count', 3)->count(),
            '4+ depósitos' => $rows->filter(fn ($row) => (int) $row->deposits_count >= 4)->count(),
        ];
    }

    public function maxDaily(): float
    {
        return max(1, collect($this->dailySeries())->max(fn ($row) => max($row['deposits'], $row['withdrawals'], $row['bets'], $row['wins'], abs($row['house']))));
    }

    public function maxProviders(): float
    {
        return max(1, collect($this->providerSeries())->max(fn ($row) => max($row['total_bet'], $row['total_win'], abs($row['house_result']))));
    }

    public function maxDistribution(): int
    {
        return max(1, collect($this->depositDistribution())->max());
    }

    public function barWidth($value, $max): int
    {
        $max = max((float) $max, 1);
        return max(4, min(100, (int) round(((float) abs($value) / $max) * 100)));
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}
