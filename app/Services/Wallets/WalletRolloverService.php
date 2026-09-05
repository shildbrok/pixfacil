<?php



namespace App\Services\Wallets;

use App\Models\Setting;
use App\Models\Wallet;
use RuntimeException;


class WalletRolloverService
{
    public const WALLET_WITHDRAWAL = 'balance_withdrawal';
    public const WALLET_DEPOSIT = 'balance';
    public const WALLET_BONUS = 'balance_bonus';

    public const ROLLOVER_DEPOSIT = 'balance_deposit_rollover';
    public const ROLLOVER_BONUS = 'balance_bonus_rollover';


    public static function applyGameResult(Wallet $wallet, float $bet, float $win): array
    {
        $bet = self::money(max(0, $bet));
        $win = self::money(max(0, $win));

        self::normalizeWallet($wallet);

        $before = self::snapshot($wallet);
        $debits = self::emptyDebits();
        $rolloverDebits = self::emptyRolloverDebits();
        $credits = self::emptyDebits();

        if (! self::rolloverEnabled()) {
            $wallet->{self::ROLLOVER_DEPOSIT} = 0;
            $wallet->{self::ROLLOVER_BONUS} = 0;
        }

        self::promoteUnlockedBalances($wallet);
        $rolloverBeforeBet = self::rolloverSnapshot($wallet);

        if ($bet > 0) {
            $debits = self::debitBet($wallet, $bet);
        }





        $rolloverDebits = self::consumeWageredRollover($wallet, $debits);

        if ($win > 0) {
            $credits = self::creditPayoutFromDebits($wallet, $win, $debits, false, $rolloverBeforeBet);
        }





        self::promoteUnlockedBalances($wallet);
        self::normalizeWallet($wallet);
        $wallet->save();

        return [
            'type_money' => self::resolveTypeMoney($debits, $credits),
            'debits' => $debits,
            'credits' => $credits,
            'rollover_debits' => $rolloverDebits,
            'rollover_before_bet' => $rolloverBeforeBet,
            'before' => $before,
            'after' => self::snapshot($wallet),
        ];
    }


    public static function debitBetOnly(Wallet $wallet, float $bet): array
    {
        $bet = self::money(max(0, $bet));
        self::normalizeWallet($wallet);

        $before = self::snapshot($wallet);
        $debits = self::emptyDebits();
        $rolloverDebits = self::emptyRolloverDebits();

        if (! self::rolloverEnabled()) {
            $wallet->{self::ROLLOVER_DEPOSIT} = 0;
            $wallet->{self::ROLLOVER_BONUS} = 0;
        }

        self::promoteUnlockedBalances($wallet);
        $rolloverBeforeBet = self::rolloverSnapshot($wallet);

        if ($bet > 0) {
            $debits = self::debitBet($wallet, $bet);
        }

        self::promoteUnlockedBalancesExcept($wallet, self::lockedSourcesFromRolloverBefore($rolloverBeforeBet));
        self::normalizeWallet($wallet);
        $wallet->save();

        return [
            'type_money' => self::resolveTypeMoney($debits),
            'debits' => $debits,
            'rollover_debits' => $rolloverDebits,
            'rollover_before_bet' => $rolloverBeforeBet,
            'before' => $before,
            'after' => self::snapshot($wallet),
        ];
    }


    public static function creditPayout(Wallet $wallet, float $win, array $debits = [], array $rolloverBeforeBet = []): array
    {
        $win = self::money(max(0, $win));
        self::normalizeWallet($wallet);

        $before = self::snapshot($wallet);
        $credits = self::emptyDebits();

        if (! self::rolloverEnabled()) {
            $wallet->{self::ROLLOVER_DEPOSIT} = 0;
            $wallet->{self::ROLLOVER_BONUS} = 0;
        }

        $rolloverBeforeBet = self::normalizeRolloverSnapshot($rolloverBeforeBet);
        self::promoteUnlockedBalancesExcept($wallet, self::lockedSourcesFromRolloverBefore($rolloverBeforeBet));

        if ($win > 0) {
            $credits = self::creditPayoutFromDebits($wallet, $win, self::normalizeDebits($debits), false, $rolloverBeforeBet);
        }

        self::promoteUnlockedBalancesExcept($wallet, self::lockedSourcesFromRolloverBefore($rolloverBeforeBet));
        self::normalizeWallet($wallet);
        $wallet->save();

        return [
            'type_money' => self::resolveTypeMoney([], $credits),
            'credits' => $credits,
            'before' => $before,
            'after' => self::snapshot($wallet),
        ];
    }

    private static function debitBet(Wallet $wallet, float $bet): array
    {
        $total = self::money((float) $wallet->{self::WALLET_WITHDRAWAL}
            + (float) $wallet->{self::WALLET_DEPOSIT}
            + (float) $wallet->{self::WALLET_BONUS});

        if ($total + 0.00001 < $bet) {
            throw new RuntimeException('INSUFFICIENT_USER_FUNDS');
        }

        $remaining = $bet;
        $debits = self::emptyDebits();

        foreach ([self::WALLET_WITHDRAWAL, self::WALLET_DEPOSIT, self::WALLET_BONUS] as $field) {
            if ($remaining <= 0) {
                break;
            }

            $available = self::money((float) $wallet->{$field});
            if ($available <= 0) {
                continue;
            }

            $use = self::money(min($available, $remaining));
            $wallet->{$field} = self::money($available - $use);
            $debits[$field] = self::money($debits[$field] + $use);
            $remaining = self::money($remaining - $use);
        }

        if ($remaining > 0.00001) {
            throw new RuntimeException('INSUFFICIENT_USER_FUNDS');
        }

        return $debits;
    }


    private static function rolloverAmountFromResult(float $bet, float $win): float
    {
        return self::money(abs(self::money($win) - self::money($bet)));
    }

    private static function consumeRollover(Wallet $wallet, float $amount, array $walletDebits = []): array
    {
        $debits = self::emptyRolloverDebits();
        $walletDebits = self::normalizeDebits($walletDebits);

        if ($amount <= 0 || ! self::rolloverEnabled()) {
            return $debits;
        }

        $remaining = $amount;

        $fields = [];


        if ((float) $walletDebits[self::WALLET_DEPOSIT] > 0) {
            $fields[] = self::ROLLOVER_DEPOSIT;
        }


        if ((float) $walletDebits[self::WALLET_BONUS] > 0) {
            $fields[] = self::ROLLOVER_BONUS;
        }


        if ($fields === []) {
            return $debits;
        }

        foreach ($fields as $field) {
            if ($remaining <= 0) {
                break;
            }

            $available = self::money((float) $wallet->{$field});
            if ($available <= 0) {
                $wallet->{$field} = 0;
                continue;
            }

            $use = self::money(min($available, $remaining));
            $wallet->{$field} = self::money($available - $use);
            $debits[$field] = self::money($debits[$field] + $use);
            $remaining = self::money($remaining - $use);
        }

        return $debits;
    }


    private static function consumeWageredRollover(Wallet $wallet, array $walletDebits): array
    {
        $debits = self::emptyRolloverDebits();

        if (! self::rolloverEnabled()) {
            return $debits;
        }

        $walletDebits = self::normalizeDebits($walletDebits);

        $map = [
            self::WALLET_DEPOSIT => self::ROLLOVER_DEPOSIT,
            self::WALLET_BONUS   => self::ROLLOVER_BONUS,
        ];

        foreach ($map as $walletField => $rolloverField) {
            $wagered = self::money((float) $walletDebits[$walletField]);
            if ($wagered <= 0) {
                continue;
            }

            $current = self::money((float) $wallet->{$rolloverField});
            if ($current <= 0) {
                $wallet->{$rolloverField} = 0;
                continue;
            }

            $use = self::money(min($current, $wagered));
            $wallet->{$rolloverField} = self::money($current - $use);
            $debits[$rolloverField] = $use;
        }

        return $debits;
    }

    private static function creditPayoutFromDebits(Wallet $wallet, float $win, array $debits, bool $save, array $rolloverBeforeBet = []): array
    {
        $remaining = $win;
        $credits = self::emptyDebits();
        $debits = self::normalizeDebits($debits);


        foreach ([self::WALLET_WITHDRAWAL, self::WALLET_DEPOSIT, self::WALLET_BONUS] as $source) {
            if ($remaining <= 0) {
                break;
            }

            $sourceAmount = self::money((float) ($debits[$source] ?? 0));
            if ($sourceAmount <= 0) {
                continue;
            }

            $creditAmount = self::money(min($sourceAmount, $remaining));
            $destination = self::destinationForSource($wallet, $source, $rolloverBeforeBet);
            $wallet->{$destination} = self::money((float) $wallet->{$destination} + $creditAmount);
            $credits[$destination] = self::money($credits[$destination] + $creditAmount);
            $remaining = self::money($remaining - $creditAmount);
        }




        if ($remaining > 0) {
            $destination = self::destinationForProfit($wallet, $debits, $rolloverBeforeBet);

            $wallet->{$destination} = self::money((float) $wallet->{$destination} + $remaining);
            $credits[$destination] = self::money($credits[$destination] + $remaining);
        }

        if ($save) {
            self::normalizeWallet($wallet);
            $wallet->save();
        }

        return $credits;
    }

    private static function destinationForSource(Wallet $wallet, string $source, array $rolloverBeforeBet = []): string
    {
        $rolloverBeforeBet = self::normalizeRolloverSnapshot($rolloverBeforeBet);

        if ($source === self::WALLET_DEPOSIT) {
            return ((float) $rolloverBeforeBet[self::ROLLOVER_DEPOSIT] > 0 || (float) $wallet->{self::ROLLOVER_DEPOSIT} > 0)
                ? self::WALLET_DEPOSIT
                : self::WALLET_WITHDRAWAL;
        }

        if ($source === self::WALLET_BONUS) {
            return ((float) $rolloverBeforeBet[self::ROLLOVER_BONUS] > 0 || (float) $wallet->{self::ROLLOVER_BONUS} > 0)
                ? self::WALLET_BONUS
                : self::WALLET_WITHDRAWAL;
        }

        return self::WALLET_WITHDRAWAL;
    }

    private static function destinationForProfit(Wallet $wallet, array $debits, array $rolloverBeforeBet = []): string
    {
        $rolloverBeforeBet = self::normalizeRolloverSnapshot($rolloverBeforeBet);

        if ((float) ($debits[self::WALLET_BONUS] ?? 0) > 0
            && ((float) $rolloverBeforeBet[self::ROLLOVER_BONUS] > 0 || (float) $wallet->{self::ROLLOVER_BONUS} > 0)) {
            return self::WALLET_BONUS;
        }

        if ((float) ($debits[self::WALLET_DEPOSIT] ?? 0) > 0
            && ((float) $rolloverBeforeBet[self::ROLLOVER_DEPOSIT] > 0 || (float) $wallet->{self::ROLLOVER_DEPOSIT} > 0)) {
            return self::WALLET_DEPOSIT;
        }

        return self::WALLET_WITHDRAWAL;
    }

    private static function promoteUnlockedBalances(Wallet $wallet): void
    {
        if ((float) $wallet->{self::ROLLOVER_DEPOSIT} <= 0) {
            $wallet->{self::ROLLOVER_DEPOSIT} = 0;
            if ((float) $wallet->{self::WALLET_DEPOSIT} > 0) {
                $wallet->{self::WALLET_WITHDRAWAL} = self::money((float) $wallet->{self::WALLET_WITHDRAWAL} + (float) $wallet->{self::WALLET_DEPOSIT});
                $wallet->{self::WALLET_DEPOSIT} = 0;
            }
        }

        if ((float) $wallet->{self::ROLLOVER_BONUS} <= 0) {
            $wallet->{self::ROLLOVER_BONUS} = 0;
            if ((float) $wallet->{self::WALLET_BONUS} > 0) {
                $wallet->{self::WALLET_WITHDRAWAL} = self::money((float) $wallet->{self::WALLET_WITHDRAWAL} + (float) $wallet->{self::WALLET_BONUS});
                $wallet->{self::WALLET_BONUS} = 0;
            }
        }
    }

    private static function promoteUnlockedBalancesExcept(Wallet $wallet, array $lockedSources = []): void
    {
        $lockedSources = array_flip($lockedSources);

        if ((float) $wallet->{self::ROLLOVER_DEPOSIT} <= 0) {
            $wallet->{self::ROLLOVER_DEPOSIT} = 0;
            if (! isset($lockedSources[self::WALLET_DEPOSIT]) && (float) $wallet->{self::WALLET_DEPOSIT} > 0) {
                $wallet->{self::WALLET_WITHDRAWAL} = self::money((float) $wallet->{self::WALLET_WITHDRAWAL} + (float) $wallet->{self::WALLET_DEPOSIT});
                $wallet->{self::WALLET_DEPOSIT} = 0;
            }
        }

        if ((float) $wallet->{self::ROLLOVER_BONUS} <= 0) {
            $wallet->{self::ROLLOVER_BONUS} = 0;
            if (! isset($lockedSources[self::WALLET_BONUS]) && (float) $wallet->{self::WALLET_BONUS} > 0) {
                $wallet->{self::WALLET_WITHDRAWAL} = self::money((float) $wallet->{self::WALLET_WITHDRAWAL} + (float) $wallet->{self::WALLET_BONUS});
                $wallet->{self::WALLET_BONUS} = 0;
            }
        }
    }

    private static function lockedSourcesFromRolloverBefore(array $rolloverBeforeBet): array
    {
        $rolloverBeforeBet = self::normalizeRolloverSnapshot($rolloverBeforeBet);
        $locked = [];

        if ((float) $rolloverBeforeBet[self::ROLLOVER_DEPOSIT] > 0) {
            $locked[] = self::WALLET_DEPOSIT;
        }

        if ((float) $rolloverBeforeBet[self::ROLLOVER_BONUS] > 0) {
            $locked[] = self::WALLET_BONUS;
        }

        return $locked;
    }

    private static function rolloverSnapshot(Wallet $wallet): array
    {
        return [
            self::ROLLOVER_DEPOSIT => self::money((float) $wallet->{self::ROLLOVER_DEPOSIT}),
            self::ROLLOVER_BONUS => self::money((float) $wallet->{self::ROLLOVER_BONUS}),
        ];
    }

    private static function normalizeRolloverSnapshot(array $snapshot): array
    {
        return [
            self::ROLLOVER_DEPOSIT => self::money(max(0, (float) ($snapshot[self::ROLLOVER_DEPOSIT] ?? 0))),
            self::ROLLOVER_BONUS => self::money(max(0, (float) ($snapshot[self::ROLLOVER_BONUS] ?? 0))),
        ];
    }

    private static function rolloverEnabled(): bool
    {
        $setting = Setting::first();
        return ! $setting || (int) ($setting->disable_rollover ?? 0) !== 1;
    }

    private static function normalizeWallet(Wallet $wallet): void
    {
        foreach ([
            self::WALLET_WITHDRAWAL,
            self::WALLET_DEPOSIT,
            self::WALLET_BONUS,
            self::ROLLOVER_DEPOSIT,
            self::ROLLOVER_BONUS,
        ] as $field) {
            $wallet->{$field} = self::money(max(0, (float) ($wallet->{$field} ?? 0)));
        }
    }

    private static function normalizeDebits(array $debits): array
    {
        $normalized = self::emptyDebits();
        foreach ($normalized as $field => $value) {
            $normalized[$field] = self::money(max(0, (float) ($debits[$field] ?? 0)));
        }
        return $normalized;
    }

    private static function emptyDebits(): array
    {
        return [
            self::WALLET_WITHDRAWAL => 0.0,
            self::WALLET_DEPOSIT => 0.0,
            self::WALLET_BONUS => 0.0,
        ];
    }

    private static function emptyRolloverDebits(): array
    {
        return [
            self::ROLLOVER_DEPOSIT => 0.0,
            self::ROLLOVER_BONUS => 0.0,
        ];
    }

    private static function resolveTypeMoney(array $debits = [], array $credits = []): string
    {
        $fields = [];
        foreach ([self::WALLET_WITHDRAWAL, self::WALLET_DEPOSIT, self::WALLET_BONUS] as $field) {
            if ((float) ($debits[$field] ?? 0) > 0 || (float) ($credits[$field] ?? 0) > 0) {
                $fields[] = $field;
            }
        }

        return count($fields) === 1 ? $fields[0] : 'mixed';
    }

    private static function snapshot(Wallet $wallet): array
    {
        return [
            self::WALLET_WITHDRAWAL => self::money((float) $wallet->{self::WALLET_WITHDRAWAL}),
            self::WALLET_DEPOSIT => self::money((float) $wallet->{self::WALLET_DEPOSIT}),
            self::WALLET_BONUS => self::money((float) $wallet->{self::WALLET_BONUS}),
            self::ROLLOVER_DEPOSIT => self::money((float) $wallet->{self::ROLLOVER_DEPOSIT}),
            self::ROLLOVER_BONUS => self::money((float) $wallet->{self::ROLLOVER_BONUS}),
            'total_balance' => self::money((float) $wallet->{self::WALLET_WITHDRAWAL} + (float) $wallet->{self::WALLET_DEPOSIT} + (float) $wallet->{self::WALLET_BONUS}),
        ];
    }

    private static function money(float $value): float
    {
        return round($value, 2);
    }
}