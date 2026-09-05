<?php



namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\AffiliateWithdraw;
use App\Models\Deposit;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPixKey;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class AffiliateController extends Controller
{
    public function index()
    {
        $userId = auth('api')->id();
        $user   = User::find($userId);

        if (!$user) {
            return response()->json(['status' => false, 'error' => 'Unauthenticated'], 401);
        }


        $code          = $user->inviter_code;
        $url           = config('app.url') . '/register?code=' . ($user->inviter_code);
        $walletDefault = Wallet::where('user_id', $userId)->first();


        $globalBaseline   = (float) (Setting::value('cpa_baseline') ?? 0);
        $globalCpaPercent = (float) (Setting::value('cpa_value') ?? 0);




        $baseline = !empty($user->affiliate_baseline)
            ? (float) $user->affiliate_baseline
            : $globalBaseline;

        $cpaPercent = !empty($user->affiliate_cpa)
            ? (float) $user->affiliate_cpa
            : $globalCpaPercent;


        $indicatedUsers = User::where('inviter', $userId)
            ->select(['id', 'name', 'email', 'created_at'])
            ->get();

        $indicationsCount = $indicatedUsers->count();
        $ids = $indicatedUsers->pluck('id')->all();


        $firstDepositsByUser = collect();
        if (!empty($ids)) {
            $sub = Deposit::query()
                ->selectRaw('user_id, MIN(created_at) as first_at')
                ->whereIn('user_id', $ids)
                ->where('status', 1)
                ->groupBy('user_id');

            $rows = Deposit::query()
                ->joinSub($sub, 'fd', function ($join) {
                    $join->on('deposits.user_id', '=', 'fd.user_id')
                        ->on('deposits.created_at', '=', 'fd.first_at');
                })
                ->where('deposits.status', 1)
                ->select(['deposits.user_id', 'deposits.amount', 'deposits.created_at'])
                ->get();


            $firstDepositsByUser = $rows->groupBy('user_id')->map(fn ($g) => $g->first());
        }

        $referrals = $indicatedUsers->map(function (User $indicated) use ($baseline, $cpaPercent, $firstDepositsByUser) {
            $firstDeposit = $firstDepositsByUser->get($indicated->id);

            $depositAmount = $firstDeposit ? (float) $firstDeposit->amount : 0.0;
            $depositDate   = $firstDeposit?->created_at;

            $registeredAt = $indicated->created_at;
            if ($registeredAt instanceof Carbon) {
                $registeredAt = $registeredAt->format('Y-m-d');
            } else {
                try {
                    $registeredAt = Carbon::parse($registeredAt)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $registeredAt = (string) $indicated->created_at;
                }
            }

            $qualified = $baseline > 0 && $depositAmount >= $baseline;
            $commission = $qualified
                ? round($depositAmount * $cpaPercent / 100, 2)
                : 0.0;

            return [
                'id'                   => $indicated->id,
                'name'                 => $indicated->name,
                'email'                => $indicated->email,
                'registered_at'        => $registeredAt,

                'first_deposit_amount' => $depositAmount,
                'first_deposit_at'     => $depositDate,

                'cpa_percent'          => $cpaPercent,
                'commission'           => $commission,
                'qualified'            => $qualified,
            ];
        });

        return response()->json([
            'status'                => true,
            'code'                  => $code,
            'url'                   => $url,
            'indications'           => $indicationsCount,
            'wallet'                => $walletDefault,

            'referrals'             => $referrals,
            'affiliate_baseline'    => $baseline,
            'affiliate_cpa_percent' => $cpaPercent,
        ]);
    }


    public function generateCode()
    {
        $code = $this->gencode();

        if (!empty($code)) {
            $user = auth('api')->user();



            try {
                if (method_exists($user, 'assignRole')) {
                    $guard = (string) (config('auth.defaults.guard') ?? 'web');
                    $roleName = 'afiliado';
                    Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
                    if (! $user->hasRole($roleName)) {
                        $user->assignRole($roleName);
                    }
                }
            } catch (\Throwable $e) { 
                Log::warning('[Affiliate] não foi possível atribuir role afiliado', ['user_id' => $user->id, 'err' => $e->getMessage()]);
            }

            if ($user->update(['inviter_code' => $code])) {
                return response()->json(['status' => true, 'message' => trans('Successfully generated code')]);
            }

            return response()->json(['error' => ''], 400);
        }

        return response()->json(['error' => ''], 400);
    }

    private function gencode()
    {
        $code = \Helper::generateCode(10);

        $checkCode = User::where('inviter_code', $code)->first();
        if (empty($checkCode)) {
            return $code;
        }

        return $this->gencode();
    }


    public function makeRequest(Request $request)
    {
        $rules = [
            'amount'     => ['required', 'numeric', 'min:0.01'],
            'pix_key_id' => ['nullable', 'integer', 'exists:user_pix_keys,id'],
            'pix_type'   => ['nullable', 'string'],
            'pix_key'    => ['nullable', 'string'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['status' => false, 'error' => 'Unauthenticated'], 401);
        }

        $userId = $user->id;
        $amount = (float) $request->amount;

        $pixKeyModel = null;

        if ($request->filled('pix_key_id')) {
            $pixKeyModel = UserPixKey::query()
                ->where('id', $request->integer('pix_key_id'))
                ->where('user_id', $userId)
                ->first();

            if (! $pixKeyModel) {
                return response()->json(['status' => false, 'error' => 'Chave Pix inválida.'], 400);
            }
        }

        $pixType = $pixKeyModel?->key_type ?: (string) $request->pix_type;
        $pixKeyValue = $pixKeyModel?->pix_key ?: (string) $request->pix_key;
        $holderCpf = preg_replace('/\D/', '', (string) ($pixKeyModel?->holder_cpf ?: $user->cpf));
        $holderName = trim((string) ($pixKeyModel?->holder_name ?: $user->name));

        $pixKeyValue = $this->normalizePixKeyForAffiliateWithdraw($pixType, $pixKeyValue);

        $pixValidation = Validator::make([
            'pix_type' => $pixType,
            'pix_key'  => $pixKeyValue,
            'cpf'      => $holderCpf,
            'name'     => $holderName,
        ], [
            'pix_type' => ['required', 'in:document,email,phoneNumber,randomKey'],
            'pix_key'  => ['required', 'string'],
            'cpf'      => ['required', 'digits:11'],
            'name'     => ['required', 'string', 'min:3', 'max:120'],
        ]);

        if ($pixValidation->fails()) {
            return response()->json($pixValidation->errors(), 400);
        }

        try {
            DB::transaction(function () use ($userId, $amount, $pixType, $pixKeyValue, $holderCpf, $holderName) {
                $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

                $commission = (float) ($wallet->refer_rewards ?? 0);

                if ($commission < $amount) {
                    throw new \RuntimeException('Você não tem saldo suficiente');
                }

                AffiliateWithdraw::create([
                    'user_id'  => $userId,
                    'amount'   => $amount,
                    'pix_key'  => $pixKeyValue,
                    'pix_type' => $pixType,
                    'cpf'      => $holderCpf,
                    'name'     => $holderName,
                    'currency' => (string) ($wallet->currency ?? 'BRL'),
                    'symbol'   => (string) ($wallet->symbol ?? 'R$'),
                    'status'   => 0,
                ]);

                $wallet->refer_rewards = (float) $wallet->refer_rewards - $amount;
                $wallet->save();
            });
        } catch (\Throwable $e) {
            report($e);
            $message = $e instanceof \RuntimeException ? $e->getMessage() : 'Não foi possível processar a solicitação.';
            return response()->json(['status' => false, 'error' => $message], 400);
        }

        return response()->json(['message' => trans('Commission withdrawal successfully carried out')], 200);
    }

    private function normalizePixKeyForAffiliateWithdraw(string $type, string $value): string
    {
        $value = trim($value);

        if (in_array($type, ['document', 'phoneNumber'], true)) {
            return preg_replace('/\D/', '', $value);
        }

        return $value;
    }
}