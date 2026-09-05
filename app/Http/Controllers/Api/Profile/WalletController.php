<?php



namespace App\Http\Controllers\Api\Profile;

use App\Helpers\Core;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\KycConfig;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\Verificacao;
use App\Models\UserPixKey;
use App\Models\GameSession;
use App\Notifications\NewWithdrawalNotification;
use App\Traits\Gateways\GeraPixTrait;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Support\AdminActionGuard;

class WalletController extends Controller
{
    use GeraPixTrait;

    public function index()
    {

        $wallet = Wallet::whereUserId(auth('api')->id())->first();

        return response()->json(['wallet' => $wallet], 200);
    }

    public function myWallet()
    {
        $wallets = Wallet::whereUserId(auth('api')->id())->get();

        return response()->json(['wallets' => $wallets], 200);
    }


    public function withdrawalFromModal($id, Request $request)
    {
        if (! $request->isMethod('post')) {
            abort(405);
        }

        abort_unless(auth()->check() && auth()->user()->can('admin'), 403);

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! app(AdminActionGuard::class)->confirm((string) $request->input('password'))) {
            Notification::make()
                ->title('PIN inválido')
                ->body('O PIN administrativo não confere.')
                ->danger()
                ->send();

            return back();
        }

        $setting = Core::getSetting();
        $resultado = null;
        $tipo = $request->input('tipo');


        $resultado = self::pixCashOutGeraPix($id, $tipo);

        if ($resultado === true) {
            Notification::make()
                ->title('Saque solicitado')
                ->body('Saque solicitado com sucesso')
                ->success()
                ->send();

            return back();
        }

        Notification::make()
            ->title('Erro no saque')
            ->body('Erro ao solicitar o saque')
            ->danger()
            ->send();

        return back();
    }

    public function cancelWithdrawalFromModal($id, Request $request)
    {
        if (! $request->isMethod('post')) {
            abort(405);
        }

        abort_unless(auth()->check() && auth()->user()->can('admin'), 403);

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! app(AdminActionGuard::class)->confirm((string) $request->input('password'))) {
            Notification::make()
                ->title('PIN inválido')
                ->body('O PIN administrativo não confere.')
                ->danger()
                ->send();

            return back();
        }

        $withdrawal = Withdrawal::query()->findOrFail($id);

        if ((int) $withdrawal->status !== 0) {
            Notification::make()
                ->title('Não foi possível cancelar')
                ->body('Esse saque não está pendente.')
                ->danger()
                ->send();

            return back();
        }

        DB::transaction(function () use ($withdrawal) {
            $wallet = Wallet::query()
                ->where('user_id', $withdrawal->user_id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new \RuntimeException('Carteira não encontrada.');
            }

            $wallet->balance_withdrawal = (float) $wallet->balance_withdrawal + (float) $withdrawal->amount;
            $wallet->save();

            $withdrawal->status = 2;
            $withdrawal->save();
        });

        Notification::make()
            ->title('Saque reembolsado')
            ->body('O saque foi cancelado e o valor retornou para o saldo de saque.')
            ->success()
            ->send();

        return back();
    }


    public function setWalletActive($id)
    {



        $userId = auth('api')->id();

        $target = Wallet::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $target) {
            return response()->json([
                'error' => 'Carteira não encontrada ou acesso não autorizado',
            ], 403);
        }

        DB::transaction(function () use ($userId, $target) {
            Wallet::where('user_id', $userId)
                ->where('id', '!=', $target->id)
                ->update(['active' => 0]);

            $target->update(['active' => 1]);
        });

        return response()->json(['wallet' => $target->fresh()], 200);
    }


    public function requestWithdrawal(Request $request)
    {
        $setting = Setting::first();

        if (! auth('api')->check()) {
            return response()->json(['error' => 'Erro ao realizar o saque'], 400);
        }

        $user = auth('api')->user();
        $userId = $user->id;
        $userName = $user->name;

        $kycConfig = KycConfig::current();

        if ($kycConfig->isWithdrawalKycRequired()) {
            $kyc = Verificacao::where('user_id', $userId)
                ->orderByDesc('id')
                ->first();

            if (! $kyc || ! $kyc->isApproved()) {
                return response()->json([
                    'error' => 'Sua conta ainda não está verificada. Complete o KYC para realizar saques.',
                    'kyc_required' => true,
                    'kyc_status' => $kyc ? $kyc->status : 'none',
                ], 403);
            }
        }

        GameSession::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhereNull('closed_at');
            })
            ->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

        // Regras-base aplicadas a TODO pedido: sem isto, um `type` fora de pix/bank
        // deixava $rules vazio, a validação passava e um `amount` negativo inflava o saldo.
        $rules = [
            'type' => ['required', 'in:pix,bank'],
            'amount' => ['required', 'numeric', 'min:' . $setting->min_withdrawal, 'max:' . $setting->max_withdrawal],
        ];

        if ($request->type === 'pix') {
            $rules['pix_key_id'] = ['required', 'integer', 'exists:user_pix_keys,id'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $pixKey = null;

        if ($request->type === 'pix') {
            $pixKey = UserPixKey::where('id', $request->pix_key_id)
                ->where('user_id', $userId)
                ->first();

            if (! $pixKey) {
                return response()->json(['error' => 'Chave Pix inválida.'], 400);
            }
        }

        if ((bool) ($user->is_influencer ?? false)) {
            return $this->processInfluencerWithdrawal($request, $user, $setting);
        }

        $amount = (float) $request->amount;

        if ($amount <= 0) {
            return response()->json(['error' => 'Valor de saque inválido.'], 400);
        }

        if ($amount > (float) $setting->max_withdrawal) {
            return response()->json([
                'error' => 'Você excedeu o limite máximo permitido de: ' . $setting->max_withdrawal,
            ], 400);
        }

        $withdrawal = null;

        try {
            DB::transaction(function () use ($user, $userId, $userName, $amount, $request, $pixKey, $setting, &$withdrawal) {

                $wallet = Wallet::where('user_id', $userId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Limite por período recontado SOB o lock da carteira, que serializa
                // as requisições do mesmo usuário. Antes a contagem era feita fora do
                // lock: dois saques simultâneos liam count() = 0 e ambos passavam.
                if (! empty($setting->withdrawal_limit)) {
                    $jaFeitos = Withdrawal::where('user_id', $userId);

                    switch ($setting->withdrawal_period) {
                        case 'daily':
                            $jaFeitos->whereDate('created_at', now()->toDateString());
                            break;
                        case 'weekly':
                            // Segunda e domingo EXPLÍCITOS. Sem isso o Carbon 3
                            // lê o primeiro dia da semana do locale, e em pt_BR
                            // ele é domingo — no Carbon 2 era sempre segunda.
                            // A janela do limite andaria um dia e o jogador que
                            // já bateu o teto ganharia cota extra no domingo.
                            $jaFeitos->whereBetween('created_at', [
                                now()->startOfWeek(Carbon::MONDAY),
                                now()->endOfWeek(Carbon::SUNDAY),
                            ]);
                            break;
                        case 'monthly':
                            $jaFeitos->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month);
                            break;
                        case 'yearly':
                            $jaFeitos->whereYear('created_at', now()->year);
                            break;
                    }

                    if ($jaFeitos->count() >= (int) $setting->withdrawal_limit) {
                        throw new \RuntimeException('Você já atingiu o limite de saques neste período.');
                    }
                }

                if ($amount > (float) $wallet->balance_withdrawal) {
                    throw new \RuntimeException('Você não tem saldo suficiente');
                }

                $data = [
                    'user_id' => $userId,
                    'amount' => \Helper::amountPrepare($amount),
                    'type' => (string) $request->type,
                    'currency' => (string) ($wallet->currency ?? 'BRL'),
                    'symbol' => (string) ($wallet->symbol ?? 'R$'),
                    'status' => 0,
                ];

                if ($request->type === 'pix' && $pixKey) {
                    $holderCpf = preg_replace('/\D/', '', (string) ($pixKey->holder_cpf ?: $user->cpf));
                    $holderName = trim((string) ($pixKey->holder_name ?: $userName));

                    if ($holderCpf === '' || $holderName === '') {
                        throw new \RuntimeException('Chave Pix sem CPF ou nome do titular.');
                    }

                    $data['pix_key'] = $this->normalizePixKeyForWithdrawal((string) $pixKey->key_type, (string) $pixKey->pix_key);
                    $data['pix_type'] = (string) $pixKey->key_type;
                    $data['cpf'] = $holderCpf;
                    $data['name'] = $holderName;
                } else {
                    $data['cpf'] = preg_replace('/\D/', '', (string) ($request->cpf ?? $user->cpf ?? ''));
                    $data['name'] = $userName;
                }

                $withdrawal = Withdrawal::create($data);

                if (! $withdrawal) {
                    throw new \RuntimeException('Erro ao realizar o saque');
                }

                $wallet->balance_withdrawal = (float) $wallet->balance_withdrawal - $amount;
                $wallet->save();
            });
        } catch (\Throwable $e) {
            report($e);
            $message = $e instanceof \RuntimeException ? $e->getMessage() : 'Não foi possível processar o saque.';
            return response()->json(['error' => $message], 400);
        }

        $autoApproved = false;

        $approvedTodayUser = Withdrawal::where('user_id', $userId)
            ->whereIn('status', [1, 9])
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if (
            ! empty($setting->withdrawal_auto_approve)
            && (float) $setting->withdrawal_auto_approve === 1.0
            && $amount <= (float) $setting->withdrawal_auto_approve_max
            && $approvedTodayUser < 3
        ) {
            try {

                // Gateway ativo de saque (settings.saque).
                $resultGateway = \App\Services\Gateways\GatewayManager::forWithdrawal()
                    ->cashOut($withdrawal->id, $request->type);

                if ($resultGateway === true) {
                    $autoApproved = true;
                    $withdrawal->refresh();
                }
            } catch (\Throwable $e) {
                Log::error('Erro ao auto-aprovar saque: ' . $e->getMessage(), [
                    'withdrawal_id' => $withdrawal->id,
                    'user_id' => $userId,
                    'amount' => $amount,
                ]);
            }
        }

        if (! $autoApproved) {
            $admins = User::role('admin')->get();

            foreach ($admins as $admin) {
                $admin->notify(new NewWithdrawalNotification($userName, $amount));
            }

            return response()->json([
                'status' => true,
                'message' => 'Saque realizado com sucesso e aguardando aprovação.',
                'kyc_required' => $kycConfig->isWithdrawalKycRequired(),
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Saque enviado para processamento.',
            'kyc_required' => $kycConfig->isWithdrawalKycRequired(),
        ], 200);
    }


    protected function processInfluencerWithdrawal(Request $request, User $user, Setting $setting)
    {
        $amount = (float) $request->amount;

        if ($amount <= 0) {
            return response()->json(['error' => 'Valor de saque inválido.'], 400);
        }

        if ($amount > (float) $setting->max_withdrawal) {
            return response()->json([
                'error' => 'Você excedeu o limite máximo permitido de: ' . $setting->max_withdrawal,
            ], 400);
        }

        try {
            DB::transaction(function () use ($user, $amount): void {

                $wallet = Wallet::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($amount > (float) $wallet->balance_withdrawal) {
                    throw new \RuntimeException('Você não tem saldo suficiente');
                }

                $wallet->balance_withdrawal = (float) $wallet->balance_withdrawal - $amount;
                $wallet->save();
            });
        } catch (\Throwable $e) {
            report($e);
            $message = $e instanceof \RuntimeException ? $e->getMessage() : 'Não foi possível processar o saque.';
            return response()->json(['error' => $message], 400);
        }

        return response()->json([
            'status' => true,
            'influencer_demo' => true,
            'message' => 'Saque demonstrativo aprovado automaticamente.',
        ], 200);
    }

    private function normalizePixKeyForWithdrawal(string $type, string $value): string
    {
        $value = trim($value);

        if (in_array($type, ['document', 'phoneNumber'], true)) {
            return preg_replace('/\D/', '', $value);
        }

        return $value;
    }
}