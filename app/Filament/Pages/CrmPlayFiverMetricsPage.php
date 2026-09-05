<?php



namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmPlayFiverMetricsPage extends Page
{
    protected static string $view = 'filament.pages.crm-playfiver-metrics-page';

    protected static ?string $title = 'Métricas PlayFiver';
    protected static ?string $navigationLabel = 'PlayFiver';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'crm-playfiver';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function totals(): array
    {
        $query = DB::table('orders')->where('providers', 'play_fiver');

        $bet = (float) (clone $query)->where('type', 'bet')->sum('amount');
        $win = (float) (clone $query)->where('type', 'win')->sum('amount');

        return [
            'bet' => $bet,
            'win' => $win,
            'loss' => max(0, $bet - $win),
            'result' => $bet - $win,
            'events' => (clone $query)->count(),
            'providers' => count($this->byProvider()),
            'games' => count($this->byGame()),
        ];
    }

    public function byProvider(): array
    {
        return DB::table('orders')
            ->leftJoin('games', function ($join) {
                $join->on('games.game_code', '=', 'orders.game')
                    ->orOn('games.game_id', '=', 'orders.game')
                    ->orOn('games.game_code', '=', 'orders.game_uuid')
                    ->orOn('games.game_id', '=', 'orders.game_uuid');
            })
            ->leftJoin('providers', 'providers.id', '=', 'games.provider_id')
            ->where('orders.providers', 'play_fiver')
            ->selectRaw("coalesce(providers.code, providers.name, 'sem_provedor') as provider_code")
            ->selectRaw("coalesce(providers.name, providers.code, 'Sem provedor') as provider_name")
            ->selectRaw("orders.providers as aggregator")
            ->selectRaw("count(*) as events")
            ->selectRaw("sum(case when orders.type = 'bet' then orders.amount else 0 end) as total_bet")
            ->selectRaw("sum(case when orders.type = 'win' then orders.amount else 0 end) as total_win")
            ->selectRaw("sum(case when orders.type = 'bet' then orders.amount else 0 end) - sum(case when orders.type = 'win' then orders.amount else 0 end) as house_result")
            ->groupBy('provider_code', 'provider_name', 'aggregator')
            ->orderByDesc('house_result')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    public function byGame(): array
    {
        return DB::table('orders')
            ->leftJoin('games', function ($join) {
                $join->on('games.game_code', '=', 'orders.game')
                    ->orOn('games.game_id', '=', 'orders.game')
                    ->orOn('games.game_code', '=', 'orders.game_uuid')
                    ->orOn('games.game_id', '=', 'orders.game_uuid');
            })
            ->leftJoin('providers', 'providers.id', '=', 'games.provider_id')
            ->where('orders.providers', 'play_fiver')
            ->selectRaw("coalesce(games.game_name, orders.game, 'sem_jogo') as game_name")
            ->selectRaw("coalesce(games.game_code, orders.game) as game_code")
            ->selectRaw("coalesce(providers.code, providers.name, 'sem_provedor') as provider_code")
            ->selectRaw("coalesce(providers.name, providers.code, 'Sem provedor') as provider_name")
            ->selectRaw("count(*) as events")
            ->selectRaw("sum(case when orders.type = 'bet' then orders.amount else 0 end) as total_bet")
            ->selectRaw("sum(case when orders.type = 'win' then orders.amount else 0 end) as total_win")
            ->selectRaw("sum(case when orders.type = 'bet' then orders.amount else 0 end) - sum(case when orders.type = 'win' then orders.amount else 0 end) as house_result")
            ->groupBy('game_name', 'game_code', 'provider_code', 'provider_name')
            ->orderByDesc('total_bet')
            ->limit(150)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    public function aggregatorWallets(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('aggregator_wallets')) {
            return [];
        }

        return DB::table('aggregator_wallets')
            ->where('provider', 'playfiver')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    public function exportProviderCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'agregador',
                'provedor_codigo',
                'provedor_nome',
                'eventos',
                'total_apostado',
                'total_ganho',
                'lucro_casa',
            ], ';');

            foreach ($this->byProvider() as $row) {
                fputcsv($handle, [
                    $row['aggregator'],
                    $row['provider_code'],
                    $row['provider_name'],
                    $row['events'],
                    $this->fmt($row['total_bet']),
                    $this->fmt($row['total_win']),
                    $this->fmt($row['house_result']),
                ], ';');
            }

            fclose($handle);
        }, 'crm_playfiver_provedores_reais_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    private function fmt($value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
