<?php



namespace App\Jobs;

use App\Exceptions\PlayFiverRateLimitException;
use App\Services\PlayFiverBalanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncPlayFiverBalancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    /** Cache key holding the count of consecutive sync failures. */
    private const FAILURE_KEY = 'playfiver_sync_consecutive_failures';

    /**
     * Only zero the aggregator wallet (which fail-closes the whole platform)
     * after this many *consecutive* failed syncs, so a single transient
     * upstream hiccup (5xx / timeout / malformed JSON) never blocks the site.
     */
    private const MAX_FAILURES_BEFORE_ZERO = 3;

    public function handle(PlayFiverBalanceService $service): void
    {
        try {
            $service->syncBalances();
            Cache::forget(self::FAILURE_KEY);
        } catch (PlayFiverRateLimitException $e) {
            Log::warning('PlayFiver retornou rate limit. Carteira mantida sem alterações.', [
                'error' => $e->getMessage(),
                'payload' => $e->payload,
            ]);
        } catch (\Throwable $e) {
            $failures = (int) Cache::get(self::FAILURE_KEY, 0) + 1;
            Cache::put(self::FAILURE_KEY, $failures, now()->addHour());

            if ($failures >= self::MAX_FAILURES_BEFORE_ZERO) {
                $service->zeroPlayFiverWallets(
                    'Saldo zerado após '.$failures.' falhas consecutivas de sincronização: '.$e->getMessage()
                );

                Log::error('Falha persistente na sincronização da PlayFiver. Carteira zerada por segurança.', [
                    'error' => $e->getMessage(),
                    'consecutive_failures' => $failures,
                ]);
            } else {
                Log::warning('Falha transitória na sincronização da PlayFiver. Saldo mantido.', [
                    'error' => $e->getMessage(),
                    'consecutive_failures' => $failures,
                    'threshold' => self::MAX_FAILURES_BEFORE_ZERO,
                ]);
            }
        }
    }
}