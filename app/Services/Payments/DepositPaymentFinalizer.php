<?php



namespace App\Services\Payments;

use App\Helpers\Core as Helper;
use App\Jobs\SendMetaEvent;
use App\Jobs\SyncPlayFiverBalancesJob;
use App\Models\AffiliateHistory;
use App\Models\ConfigRoundsFree;
use App\Models\Deposit;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\NewDepositNotification;
use App\Services\PlayFiverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class DepositPaymentFinalizer
{
    public static function finalize(string $paymentId, string $externalId, ?Request $request = null, string $gateway = ''): bool
    {
        return DB::transaction(function () use ($paymentId, $externalId, $request, $gateway) {
            $transaction = Transaction::where('payment_id', $paymentId)
                ->where('idUnico', $externalId)
                ->where('status', 0)
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                return false;
            }


            if ($request) {
                $payloadAmount = $request->input('amount') ?? $request->input('value') ?? null;
                if ($payloadAmount !== null) {
                    $payloadAmount = (float) $payloadAmount;
                    $expected = (float) $transaction->price;
                    if ($payloadAmount > 0 && abs($payloadAmount - $expected) > 0.01) {
                        Log::warning('[PAYMENTS] callback com amount divergente', [
                            'gateway' => $gateway,
                            'transaction_id' => $paymentId,
                            'expected' => $expected,
                            'payload' => $payloadAmount,
                            'explicacao' => 'O valor confirmado pelo gateway é diferente do valor da transação. Pode ser payload incorreto, gateway errado ou tentativa inválida.',
                        ]);
                        return false;
                    }
                }
            }

            $user = User::find($transaction->user_id);
            $wallet = Wallet::where('user_id', $transaction->user_id)->lockForUpdate()->first();
            if (! $wallet || ! $user) {
                return false;
            }

            $setting = Setting::first();
            if (! $setting) {
                return false;
            }






            if (is_null($wallet->welcome_bonus_at)) {
                $bonus = Helper::porcentagem_xn($setting->initial_bonus, $transaction->price);
                $wallet->balance_bonus = (float) $wallet->balance_bonus + (float) $bonus;

                if ((int) $setting->disable_rollover !== 1) {
                    $wallet->balance_bonus_rollover = (float) $wallet->balance_bonus_rollover
                        + ((float) $bonus * max(0, (float) $setting->rollover));
                }

                $wallet->welcome_bonus_at = now();
            }




            $roundsFreeToGrant = ConfigRoundsFree::with('game')
                ->active()
                ->where('value', '<=', (float) $transaction->price)
                ->orderBy('value', 'desc')
                ->first();

            if ($roundsFreeToGrant) {
                $rfUsername = $user->email;
                $rfGameCode = $roundsFreeToGrant->game_code;
                $rfSpins    = (int) $roundsFreeToGrant->spins;

                DB::afterCommit(function () use ($rfUsername, $rfGameCode, $rfSpins, $user) {
                    try {
                        PlayFiverService::RoundsFree([
                            'username'  => $rfUsername,
                            'game_code' => $rfGameCode,
                            'rounds'    => $rfSpins,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('[RoundsFree] erro ao disparar (afterCommit)', [
                            'user_id'   => $user->id,
                            'game_code' => $rfGameCode,
                            'spins'     => $rfSpins,
                            'err'       => $e->getMessage(),
                        ]);
                    }
                });
            }



            if ((int) $setting->disable_rollover === 1) {
                $wallet->balance_deposit_rollover = 0;
                $wallet->balance_bonus_rollover = 0;
            } else {
                $wallet->balance_deposit_rollover = (float) $wallet->balance_deposit_rollover
                    + ((float) $transaction->price * max(0, (float) $setting->rollover_deposit));
            }


            $wallet->balance = (float) $wallet->balance + (float) $transaction->price;
            $wallet->save();


            $transaction->status = 1;
            $transaction->save();



            // Mesma idempotency_key do 'pending' mandado na geração do PIX: é a
            // virada para 'paid' que conta a venda e dispara a conversão na Meta.
            // O gateway é o provider; o método continua sendo pix.
            $betcrmAmount   = (float) $transaction->price;
            $betcrmKey      = 'dep_' . $paymentId;
            $betcrmProvider = (string) $gateway;
            DB::afterCommit(function () use ($user, $betcrmAmount, $betcrmKey, $betcrmProvider) {
                if ($user) {
                    \App\Services\BetCrm\BetCrmService::sendDeposit($user, $betcrmAmount, $betcrmKey, [
                        'status'   => \App\Services\BetCrm\BetCrmService::STATUS_PAID,
                        'method'   => 'pix',
                        'provider' => $betcrmProvider,
                        'currency' => 'BRL',
                    ]);
                }
            });


            $deposit = Deposit::where('payment_id', $paymentId)->lockForUpdate()->first();
            if ($deposit && (int) $deposit->status === 0) {


                $affHistoryCPA = AffiliateHistory::where('user_id', $user->id)
                    ->where('commission_type', 'cpa')
                    ->where('status', 0)
                    ->lockForUpdate()
                    ->first();

                if ($affHistoryCPA) {
                    $sponsorCpa = User::find($user->inviter);
                    if ($sponsorCpa) {
                        $deposited_amount = (float) $transaction->price;

                        $affiliateBaseline = $sponsorCpa->affiliate_baseline;
                        if (empty($affiliateBaseline)) {
                            $affiliateBaseline = $setting->cpa_baseline;
                        }





                        $accumulatedWithCurrent = (float) $affHistoryCPA->deposited_amount + (float) $transaction->price;

                        if (
                            $accumulatedWithCurrent >= (float) $affiliateBaseline ||
                            (float) $deposit->amount >= (float) $affiliateBaseline
                        ) {
                            $walletCpa = Wallet::where('user_id', $affHistoryCPA->inviter)
                                ->lockForUpdate()
                                ->first();

                            if ($walletCpa) {
                                $percentCpa = $sponsorCpa->affiliate_cpa;
                                if (empty($percentCpa)) {
                                    $percentCpa = $setting->cpa_value;
                                }

                                $commissionValue = Helper::porcentagem_xn($percentCpa, $deposit->amount);
                                $walletCpa->refer_rewards = (float) $walletCpa->refer_rewards + (float) $commissionValue;
                                $walletCpa->save();

                                $affHistoryCPA->status = 1;
                                $affHistoryCPA->deposited = $deposited_amount;
                                $affHistoryCPA->commission_paid = (float) $commissionValue;
                                $affHistoryCPA->save();
                            }
                        } else {
                            $affHistoryCPA->deposited_amount = (float) $affHistoryCPA->deposited_amount + (float) $transaction->price;
                            $affHistoryCPA->save();
                        }
                    }
                }


                $deposit->status = 1;
                $deposit->save();

                DB::afterCommit(function () use ($user, $transaction) {

                    try {
                        $admins = User::role('admin')->get();
                        foreach ($admins as $admin) {
                            $admin->notify(new NewDepositNotification($user->name, $transaction->price));
                        }
                    } catch (\Throwable $e) {

                    }


                    try {
                        $eventId = 'dep_' . $transaction->id;

                        // afterResponse (não dispatch): roda no processo web após a
                        // resposta, sem depender de um worker de fila. Antes ia para a
                        // fila database e, sem worker (caso do CloudPanel), a conversão
                        // da Meta ficava presa na tabela jobs e nunca era enviada.
                        SendMetaEvent::dispatchAfterResponse(
                            'Purchase',
                            [
                                'email' => $user->email,
                                'phone' => $user->phone ?? null,
                                'first_name' => $user->name ?? null,
                                'external_id' => (string) $user->id,
                                'fbp' => $transaction->fbp,
                                'fbc' => $transaction->fbc,
                            ],
                            [
                                'value' => (float) $transaction->price,
                                'currency' => 'BRL',
                                'order_id' => $transaction->id,
                            ],
                            $eventId,
                            [
                                'action_source' => 'website',
                                'event_source_url' => $transaction->source_url ?: config('app.url') . '/deposit',
                                'client_ip' => $transaction->client_ip,
                                'user_agent' => $transaction->user_agent,
                                'test_event' => app()->environment(['local', 'development', 'staging']),
                            ]
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Erro ao enviar evento Meta CAPI (deposit): ' . $e->getMessage(), [
                            'transaction_id' => $transaction->id ?? null,
                            'user_id' => $user->id ?? null,
                        ]);
                    }


                    try {
                        if (random_int(1, 3) === 2) {
                            // A-02: roda APÓS enviar a resposta ao gateway (nao bloqueia o webhook,
                            // dispensa worker). Antes era dispatchSync(), que travava a resposta
                            // e podia causar timeout/reenvio do gateway.
                            SyncPlayFiverBalancesJob::dispatchAfterResponse();
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Falha ao executar sincronização PlayFiver após depósito.', [
                            'transaction_id' => $transaction->id ?? null,
                            'user_id' => $user->id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            }

            return true;
        });
    }
}