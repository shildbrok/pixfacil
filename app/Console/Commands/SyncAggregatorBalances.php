<?php



namespace App\Console\Commands;

use App\Jobs\SyncPlayFiverBalancesJob;
use Illuminate\Console\Command;

class SyncAggregatorBalances extends Command
{
    protected $signature = 'aggregator:sync-balances';

    protected $description = 'Sincroniza os saldos do agregador PlayFiver';

    public function handle(): int
    {
        SyncPlayFiverBalancesJob::dispatchSync();

        $this->info('Sincronização do agregador executada.');

        return self::SUCCESS;
    }
}
