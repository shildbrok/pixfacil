<?php



namespace App\Services;

use App\Exceptions\PlayFiverRateLimitException;
use App\Models\AggregatorWallet;
use App\Models\GamesKey;
use App\Notifications\AggregatorWalletLowBalanceNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use App\Support\MailNotificationGuard;
use RuntimeException;

class PlayFiverBalanceService
{
    public const CACHE_KEY = 'aggregator_wallet_gate_status';


    public const PRIMARY_WALLET_KEY = 'carteira-playfiver-slots';

    public function syncBalances(): array
    {
        $payload = $this->fetchBalances();
        $balances = Arr::get($payload, 'data', []);

        if (! is_array($balances) || $balances === []) {
            throw new RuntimeException('A PlayFiver não retornou carteiras válidas.');
        }

        $synced = [];
        $now = now();

        foreach ($balances as $name => $balance) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $wallet = AggregatorWallet::query()->firstOrNew([
                'provider' => 'playfiver',
                'wallet_key' => $this->walletKeyFromName($name),
            ]);

            $wallet->name = $name;
            $wallet->balance = $this->normalizeBalance($balance);
            $wallet->currency = $wallet->currency ?: 'BRL';
            $wallet->last_synced_at = $now;
            $wallet->last_error = null;
            $wallet->meta = [
                'raw_name' => $name,
                'source' => 'playfiver:/api/v2/balances',
            ];

            if (! $wallet->exists) {
                $wallet->is_primary = $this->isPrimaryWalletName($name);
                $wallet->info_only = ! $this->isPrimaryWalletName($name);
            }

            $wallet->save();

            if (MailNotificationGuard::canSend() && $wallet->notify_enabled && ! empty($wallet->notify_email)) {
                $this->handleWalletNotification($wallet);
            }

            $synced[] = $wallet->fresh();
        }

        Cache::forget(self::CACHE_KEY);

        return [
            'status' => true,
            'wallets' => $synced,
            'blocked' => $this->currentGateStatus(),
        ];
    }

    public function fetchBalances(): array
    {
        $keys = GamesKey::query()->first();
        $agentToken = (string) ($keys?->playfiver_token ?? '');
        $secretKey = (string) ($keys?->playfiver_secret ?? '');

        if ($agentToken === '' || $secretKey === '') {
            throw new RuntimeException('As credenciais PlayFiver não estão configuradas em Chaves dos Jogos.');
        }

        $url = rtrim((string) ($keys?->playfiver_url ?: 'https://api.playfivers.com'), '/').'/api/v2/balances';

        $response = Http::withOptions(['force_ip_resolve' => 'v4'])->acceptJson()
            ->timeout(20)
            ->get($url, [
                'agentToken' => $agentToken,
                'secretKey' => $secretKey,
            ]);

        $json = $response->json();

        if ($response->status() === 429) {
            $message = data_get($json, 'message');
            $actionType = data_get($json, 'actions.0.type');

            if ($message === 'Você foi limitado devido ao grande número de solicitações..'
                && $actionType === 'block') {
                throw new PlayFiverRateLimitException(
                    $message,
                    is_array($json) ? $json : ['body' => $response->body()]
                );
            }
        }

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao consultar saldo na PlayFiver: HTTP '.$response->status().'.');
        }

        if (! is_array($json)) {
            throw new RuntimeException('Resposta inválida da PlayFiver ao consultar saldos.');
        }

        if ((int) Arr::get($json, 'status', 0) !== 1) {
            throw new RuntimeException('A PlayFiver respondeu sem sucesso ao consultar saldos.');
        }

        return $json;
    }

    public function zeroPlayFiverWallets(string $reason = 'Saldo zerado por falha de sincronização da PlayFiver.'): void
    {
        AggregatorWallet::query()
            ->where('provider', 'playfiver')
            ->where('wallet_key', self::PRIMARY_WALLET_KEY)
            ->update([
                'balance' => 0,
                'last_error' => $reason,
                'last_synced_at' => now(),
                'updated_at' => now(),
            ]);

        Cache::forget(self::CACHE_KEY);

        Log::warning('Carteira PlayFiver zerada por segurança.', [
            'provider' => 'playfiver',
            'wallet_key' => 'carteira-playfiver-slots',
            'reason' => $reason,
        ]);
    }

    public function currentGateStatus(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(15), function () {
            $blockingWallets = AggregatorWallet::query()
                ->where('is_primary', true)
                ->get()
                ->filter(fn (AggregatorWallet $wallet) => $wallet->isBlockingNow())
                ->values();

            $blocked = $blockingWallets->isNotEmpty();
            $wallet = $blockingWallets->first();

            return [
                'blocked' => $blocked,
                'wallet_id' => $wallet?->id,
                'wallet_name' => $wallet?->name,
                'balance' => $wallet ? (float) $wallet->balance : null,
                'threshold' => $wallet ? (float) ($wallet->fixedBlockThreshold() ?? 0) : null,
                'message' => $blocked
                    ? sprintf(
                        'Serviço temporariamente indisponível. %s abaixo do mínimo operacional (%s).',
                        $wallet->name,
                        $this->formatMoney((float) ($wallet->fixedBlockThreshold() ?? 0))
                    )
                    : null,
            ];
        });
    }

    public function gateForAction(string $action = 'general'): array
    {
        $status = $this->currentGateStatus();

        if (! ($status['blocked'] ?? false)) {
            return [
                'allowed' => true,
                'status_code' => 200,
                'message' => null,
            ];
        }

        $map = [
            'login' => 'Login temporariamente bloqueado.',
            'register' => 'Cadastro temporariamente bloqueado.',
            'game' => 'Abertura de jogo temporariamente bloqueada.',
            'general' => 'Serviço temporariamente bloqueado.',
        ];

        return [
            'allowed' => false,
            'status_code' => 423,
            'message' => trim(($map[$action] ?? $map['general']).' '.($status['message'] ?? '')),
            'details' => $status,
        ];
    }

    private function handleWalletNotification(AggregatorWallet $wallet): void
    {
        $below = $wallet->isBelowNotificationThreshold();

        if ($below && ! $wallet->was_below_notify_threshold) {
            if (! MailNotificationGuard::canSend()) {
                return;
            }

            try {
                Notification::route('mail', $wallet->notify_email)
                    ->notify(new AggregatorWalletLowBalanceNotification($wallet));

                $wallet->forceFill([
                    'was_below_notify_threshold' => true,
                    'last_notified_at' => now(),
                ])->save();
            } catch (\Throwable $e) {
                Log::warning('Falha ao enviar alerta de saldo do agregador.', [
                    'wallet_id' => $wallet->id,
                    'wallet' => $wallet->name,
                    'email' => $wallet->notify_email,
                    'error' => $e->getMessage(),
                ]);
            }

            return;
        }

        if (! $below && $wallet->was_below_notify_threshold) {
            $wallet->forceFill([
                'was_below_notify_threshold' => false,
            ])->save();
        }
    }

    private function walletKeyFromName(string $name): string
    {
        $slug = Str::slug(Str::ascii($name));

        return $slug !== '' ? $slug : 'wallet-'.md5($name);
    }

    private function normalizeBalance(mixed $balance): float
    {
        if (is_numeric($balance)) {
            return round((float) $balance, 2);
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', (string) $balance);




        $lastComma = strrpos($normalized, ',');
        $lastDot   = strrpos($normalized, '.');

        if ($lastComma !== false && $lastComma > ($lastDot === false ? -1 : $lastDot)) {

            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {

            $normalized = str_replace(',', '', $normalized);
        }

        return round((float) $normalized, 2);
    }

    private function isPrimaryWalletName(string $name): bool
    {



        return $this->walletKeyFromName($name) === self::PRIMARY_WALLET_KEY;
    }

    private function formatMoney(float $amount): string
    {
        return 'R$ '.number_format($amount, 2, ',', '.');
    }
}