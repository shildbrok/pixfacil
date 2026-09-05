<?php

namespace App\Services\Gateways;

use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

final class WithdrawalDispatchClaim
{
    public static function claim(int $withdrawalId, string $gateway, ?callable $paymentIdFactory = null): ?Withdrawal
    {
        return DB::transaction(function () use ($withdrawalId, $gateway, $paymentIdFactory): ?Withdrawal {
            $withdrawal = Withdrawal::query()->whereKey($withdrawalId)->lockForUpdate()->first();
            if (! $withdrawal || (int) $withdrawal->status !== 0) {
                return null;
            }

            if ($paymentIdFactory && empty($withdrawal->payment_id)) {
                $withdrawal->payment_id = (string) $paymentIdFactory($withdrawal);
            }

            $withdrawal->status = 9;
            $withdrawal->gateway = $gateway;
            $withdrawal->save();

            return $withdrawal->fresh();
        }, 3);
    }

    public static function releaseToPending(int $withdrawalId): void
    {
        DB::transaction(function () use ($withdrawalId): void {
            $withdrawal = Withdrawal::query()->whereKey($withdrawalId)->lockForUpdate()->first();
            if ($withdrawal && (int) $withdrawal->status === 9) {
                $withdrawal->status = 0;
                $withdrawal->save();
            }
        }, 3);
    }
}
