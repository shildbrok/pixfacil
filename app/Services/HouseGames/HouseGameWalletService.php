<?php

namespace App\Services\HouseGames;

use App\Models\HouseGame;
use App\Models\HouseGameRound;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallets\WalletRolloverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HouseGameWalletService
{
    public static function cookieName(string $slug): string
    {
        return 'pf_retro_' . preg_replace('/[^a-z0-9_\-]/i', '', $slug);
    }

    public function start(HouseGame $game, User $user, float $bet, string $clientEventId): array
    {
        $bet = round($bet, 2);

        if (! $game->active) {
            throw new RuntimeException('Jogo indisponível no momento.');
        }

        if ($bet <= 0 || $bet < (float) $game->min_bet || $bet > (float) $game->max_bet) {
            throw new RuntimeException(sprintf(
                'A aposta deve ficar entre R$ %s e R$ %s.',
                number_format((float) $game->min_bet, 2, ',', '.'),
                number_format((float) $game->max_bet, 2, ',', '.')
            ));
        }

        if (! Str::isUuid($clientEventId)) {
            throw new RuntimeException('Identificador de aposta inválido.');
        }

        return $this->withUserLock($user->id, function () use ($game, $user, $bet, $clientEventId): array {
            $existing = HouseGameRound::query()
                ->where('user_id', $user->id)
                ->where('client_event_id', $clientEventId)
                ->first();

            if ($existing) {
                if ((int) $existing->house_game_id !== (int) $game->id) {
                    throw new RuntimeException('Identificador de aposta já utilizado.');
                }

                $token = $this->rotateEngineToken($existing);
                return ['round' => $existing->fresh(), 'engine_token' => $token, 'reused' => true];
            }

            HouseGameRound::query()
                ->where('user_id', $user->id)
                ->where('status', HouseGameRound::STATUS_OPEN)
                ->update([
                    'status' => HouseGameRound::STATUS_LOST,
                    'payout' => 0,
                    'settled_at' => now(),
                    'failure_reason' => 'Substituída por uma nova rodada.',
                    'updated_at' => now(),
                ]);

            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('active', 1)
                ->first() ?: Wallet::query()->where('user_id', $user->id)->first();

            if (! $wallet) {
                throw new RuntimeException('Carteira não encontrada.');
            }

            if ((float) $wallet->total_balance + 0.00001 < $bet) {
                throw new RuntimeException('Saldo insuficiente para essa aposta.');
            }

            $meta = round($bet * (float) $game->meta_multiplier, 2);
            $maxPayout = round($bet * (float) $game->max_win_multiplier, 2);
            $timeout = max(60, (int) $game->round_timeout_seconds);

            $round = HouseGameRound::query()->create([
                'round_uuid' => (string) Str::uuid(),
                'client_event_id' => $clientEventId,
                'user_id' => $user->id,
                'house_game_id' => $game->id,
                'game_slug' => $game->slug,
                'bet' => $bet,
                'meta_amount' => $meta,
                'max_payout' => $maxPayout,
                'payout' => 0,
                'status' => HouseGameRound::STATUS_OPENING,
                'opened_at' => now(),
                'expires_at' => now()->addSeconds($timeout),
            ]);

            try {
                $result = WalletRolloverService::applyGameResult($wallet, $bet, 0);
                $token = bin2hex(random_bytes(32));

                $round->update([
                    'status' => HouseGameRound::STATUS_OPEN,
                    'type_money' => (string) ($result['type_money'] ?? ''),
                    'debits' => $result['debits'] ?? [],
                    'rollover_before_bet' => $result['rollover_before_bet'] ?? [],
                    'engine_token_hash' => hash('sha256', $token),
                ]);

                return ['round' => $round->fresh(), 'engine_token' => $token, 'reused' => false];
            } catch (Throwable $e) {
                $round->update([
                    'status' => HouseGameRound::STATUS_CANCELED,
                    'settled_at' => now(),
                    'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
                ]);

                if ($e instanceof RuntimeException && $e->getMessage() === 'INSUFFICIENT_USER_FUNDS') {
                    throw new RuntimeException('Saldo insuficiente para essa aposta.');
                }

                throw $e;
            }
        });
    }

    public function launch(HouseGame $game, User $user): array
    {
        return $this->withUserLock($user->id, function () use ($game, $user): array {
            $round = $this->openRoundForUser($game, $user->id);
            if (! $round) {
                throw new RuntimeException('Nenhuma rodada em andamento.');
            }

            if ($this->expireIfNeeded($round)) {
                throw new RuntimeException('A rodada expirou. Inicie uma nova partida.');
            }

            $token = $this->rotateEngineToken($round);
            $round->update(['launched_at' => $round->launched_at ?: now()]);

            return ['round' => $round->fresh(), 'engine_token' => $token];
        });
    }

    public function forfeit(HouseGame $game, User $user): ?HouseGameRound
    {
        return $this->withUserLock($user->id, function () use ($game, $user): ?HouseGameRound {
            $round = $this->openRoundForUser($game, $user->id);
            if (! $round) {
                return null;
            }

            HouseGameRound::query()
                ->whereKey($round->id)
                ->where('status', HouseGameRound::STATUS_OPEN)
                ->update([
                    'status' => HouseGameRound::STATUS_LOST,
                    'payout' => 0,
                    'settled_at' => now(),
                    'failure_reason' => 'Rodada encerrada pelo jogador.',
                    'updated_at' => now(),
                ]);

            return $round->fresh();
        });
    }

    public function engineInfo(HouseGame $game, HouseGameRound $round): array
    {
        if ($this->expireIfNeeded($round)) {
            throw new RuntimeException('A rodada expirou.');
        }

        if ($round->status !== HouseGameRound::STATUS_OPEN) {
            throw new RuntimeException('A rodada não está disponível.');
        }

        $round->update(['launched_at' => $round->launched_at ?: now()]);
        $bet = (float) $round->bet;

        return [
            'bet' => $bet,
            'meta' => (float) $round->meta_amount,
            'max_payout' => (float) $round->max_payout,
            'settings' => $game->engineSettings(),
            'round_id' => $round->round_uuid,
            'last_balance' => ['amount' => $bet],
            'fake' => 0,
        ];
    }

    public function settleWin(HouseGame $game, HouseGameRound $round): array
    {
        return $this->withUserLock($round->user_id, function () use ($game, $round): array {
            $round = HouseGameRound::query()->whereKey($round->id)->firstOrFail();

            if ($this->expireIfNeeded($round)) {
                throw new RuntimeException('A rodada expirou.');
            }

            if ($round->status !== HouseGameRound::STATUS_OPEN) {
                throw new RuntimeException('Rodada já liquidada.');
            }

            $openedAt = $round->launched_at ?: $round->opened_at ?: $round->created_at;
            $elapsed = $openedAt ? $openedAt->diffInSeconds(now()) : 0;
            if ($elapsed < max(0, (int) $game->min_win_seconds)) {
                throw new RuntimeException('A rodada terminou rápido demais para ser liquidada.');
            }

            $decision = $this->serverSettlementDecision($round);

            if (! $decision['won']) {
                $claimedOk = HouseGameRound::query()
                    ->whereKey($round->id)
                    ->where('status', HouseGameRound::STATUS_OPEN)
                    ->update([
                        'status' => HouseGameRound::STATUS_LOST,
                        'client_claim' => 0,
                        'payout' => 0,
                        'settled_at' => now(),
                        'failure_reason' => 'Resultado financeiro determinado pelo servidor.',
                        'updated_at' => now(),
                    ]);

                if ($claimedOk !== 1) {
                    throw new RuntimeException('Rodada já liquidada.');
                }

                $wallet = Wallet::query()
                    ->where('user_id', $round->user_id)
                    ->where('active', 1)
                    ->first() ?: Wallet::query()->where('user_id', $round->user_id)->first();

                return [
                    'round' => HouseGameRound::find($round->id),
                    'outcome' => 'lost',
                    'payout' => 0.0,
                    'balance' => (float) ($wallet?->total_balance ?? 0),
                    'credit' => null,
                ];
            }

            $payout = (float) $decision['payout'];

            $claimedOk = HouseGameRound::query()
                ->whereKey($round->id)
                ->where('status', HouseGameRound::STATUS_OPEN)
                ->update([
                    'status' => HouseGameRound::STATUS_SETTLING,
                    'client_claim' => 0,
                    'payout' => $payout,
                    'updated_at' => now(),
                ]);

            if ($claimedOk !== 1) {
                throw new RuntimeException('Rodada já liquidada.');
            }

            $wallet = Wallet::query()
                ->where('user_id', $round->user_id)
                ->where('active', 1)
                ->first() ?: Wallet::query()->where('user_id', $round->user_id)->first();

            if (! $wallet) {
                HouseGameRound::whereKey($round->id)->update([
                    'status' => HouseGameRound::STATUS_PAYOUT_FAILED,
                    'failure_reason' => 'Carteira não encontrada no pagamento.',
                    'settled_at' => now(),
                ]);
                throw new RuntimeException('Não foi possível localizar a carteira para pagar a rodada.');
            }

            try {
                $credit = WalletRolloverService::creditPayout(
                    $wallet,
                    $payout,
                    is_array($round->debits) ? $round->debits : [],
                    is_array($round->rollover_before_bet) ? $round->rollover_before_bet : []
                );

                HouseGameRound::whereKey($round->id)->update([
                    'status' => HouseGameRound::STATUS_WON,
                    'settled_at' => now(),
                    'failure_reason' => null,
                    'updated_at' => now(),
                ]);

                return [
                    'round' => HouseGameRound::find($round->id),
                    'outcome' => 'won',
                    'payout' => $payout,
                    'balance' => (float) $wallet->fresh()->total_balance,
                    'credit' => $credit,
                ];
            } catch (Throwable $e) {
                HouseGameRound::whereKey($round->id)->update([
                    'status' => HouseGameRound::STATUS_PAYOUT_FAILED,
                    'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
                    'settled_at' => now(),
                    'updated_at' => now(),
                ]);
                throw $e;
            }
        });
    }

    private function serverSettlementDecision(HouseGameRound $round): array
    {
        $betCents = max(1, (int) round((float) $round->bet * 100));
        $maxCents = max(1, (int) round((float) $round->max_payout * 100));
        $metaCents = max(1, (int) round((float) $round->meta_amount * 100));
        $minCents = min($metaCents, $maxCents);

        $payoutCents = random_int($minCents, $maxCents);
        $rtpPercent = (float) config('services.security.retro_server_rtp_percent', 90);
        $rtpPercent = min(100.0, max(0.0, $rtpPercent));

        if ($rtpPercent <= 0.0) {
            return ['won' => false, 'payout' => 0.0];
        }

        $probability = min(1.0, (($rtpPercent / 100.0) * $betCents) / $payoutCents);
        $scale = 1_000_000;
        $threshold = (int) floor($probability * $scale);
        $won = $threshold >= $scale || random_int(1, $scale) <= $threshold;

        return [
            'won' => $won,
            'payout' => $won ? round($payoutCents / 100, 2) : 0.0,
        ];
    }

    public function settleLoss(HouseGame $game, HouseGameRound $round): array
    {
        return $this->withUserLock($round->user_id, function () use ($round): array {
            $round = HouseGameRound::query()->whereKey($round->id)->firstOrFail();

            if ($round->status === HouseGameRound::STATUS_OPEN) {
                HouseGameRound::query()->whereKey($round->id)->where('status', HouseGameRound::STATUS_OPEN)->update([
                    'status' => HouseGameRound::STATUS_LOST,
                    'payout' => 0,
                    'settled_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $wallet = Wallet::query()->where('user_id', $round->user_id)->where('active', 1)->first()
                ?: Wallet::query()->where('user_id', $round->user_id)->first();

            return [
                'round' => HouseGameRound::find($round->id),
                'balance' => (float) ($wallet?->total_balance ?? 0),
            ];
        });
    }

    public function roundFromEngineToken(HouseGame $game, ?string $token): ?HouseGameRound
    {
        if (! $token || strlen($token) < 32) {
            return null;
        }

        return HouseGameRound::query()
            ->where('house_game_id', $game->id)
            ->where('game_slug', $game->slug)
            ->where('engine_token_hash', hash('sha256', $token))
            ->whereIn('status', [HouseGameRound::STATUS_OPEN, HouseGameRound::STATUS_SETTLING])
            ->orderByDesc('id')
            ->first();
    }

    private function openRoundForUser(HouseGame $game, int $userId): ?HouseGameRound
    {
        return HouseGameRound::query()
            ->where('user_id', $userId)
            ->where('house_game_id', $game->id)
            ->where('status', HouseGameRound::STATUS_OPEN)
            ->orderByDesc('id')
            ->first();
    }

    private function expireIfNeeded(HouseGameRound $round): bool
    {
        if ($round->status !== HouseGameRound::STATUS_OPEN) {
            return $round->status === HouseGameRound::STATUS_EXPIRED;
        }

        if ($round->expires_at && $round->expires_at->isPast()) {
            HouseGameRound::query()->whereKey($round->id)->where('status', HouseGameRound::STATUS_OPEN)->update([
                'status' => HouseGameRound::STATUS_EXPIRED,
                'payout' => 0,
                'settled_at' => now(),
                'failure_reason' => 'Tempo limite da rodada excedido.',
                'updated_at' => now(),
            ]);
            $round->status = HouseGameRound::STATUS_EXPIRED;
            return true;
        }

        return false;
    }

    private function rotateEngineToken(HouseGameRound $round): string
    {
        $token = bin2hex(random_bytes(32));
        $round->update(['engine_token_hash' => hash('sha256', $token)]);
        return $token;
    }

    private function withUserLock(int $userId, callable $callback): mixed
    {
        $key = 'house_game_wallet_' . $userId;
        $locked = false;

        try {
            if (DB::connection()->getDriverName() === 'mysql') {
                $result = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$key]);
                $locked = (int) ($result->acquired ?? 0) === 1;
                if (! $locked) {
                    throw new RuntimeException('Sua carteira está ocupada. Tente novamente em alguns segundos.');
                }
            }

            return $callback();
        } finally {
            if ($locked) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$key]);
                } catch (Throwable) {
                }
            }
        }
    }
}
