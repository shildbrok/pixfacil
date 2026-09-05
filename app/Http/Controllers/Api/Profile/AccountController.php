<?php



namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\Game; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 

class AccountController extends Controller
{

    public function overview(Request $request)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $userId = $user->id;

        $wallet = Wallet::where('user_id', $userId)
            ->where('active', 1)
            ->first();

        $setting = Setting::first();


        $ordersQuery = Order::where('user_id', $userId);

        $totalEarnings = (clone $ordersQuery)
            ->where('type', 'win')
            ->sum('amount');

        $totalBetsCount = (clone $ordersQuery)
            ->where('type', 'bet')
            ->count();

        $sumBets = (clone $ordersQuery)
            ->where('type', 'bet')
            ->sum('amount');

        $lastBets = (clone $ordersQuery)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();


        $lastDeposits = Deposit::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $lastWithdrawals = Withdrawal::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->take(5)
            ->get();


        $depositLimits = [
            'min_deposit' => (float) ($setting->min_deposit ?? 0),
            'max_deposit' => (float) ($setting->max_deposit ?? 0),
        ];

        $withdrawalLimits = [
            'min_withdrawal'              => (float) ($setting->min_withdrawal ?? 0),
            'max_withdrawal'              => (float) ($setting->max_withdrawal ?? 0),
            'withdrawal_limit'            => (int)   ($setting->withdrawal_limit ?? 0),
            'withdrawal_period'           => $setting->withdrawal_period ?? null,
            'withdrawal_auto_approve'     => (bool)  ($setting->withdrawal_auto_approve ?? false),
            'withdrawal_auto_approve_max' => (float) ($setting->withdrawal_auto_approve_max ?? 0),
        ];


        $bonus = [
            'balance_bonus'          => (float) ($wallet->balance_bonus ?? 0),
            'balance_bonus_rollover' => (float) ($wallet->balance_bonus_rollover ?? 0),
        ];

        $affiliate = [

            'affiliate_baseline'    => (float) ($user->affiliate_baseline ?? 0),

            'affiliate_cpa_percent' => (float) ($user->affiliate_cpa ?? 0),
        ];


        return response()->json([
            'status' => true,

            'user'   => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,


                'document'   => $user->document ?? $user->cpf ?? null,
                'cpf'        => $user->cpf ?? $user->document ?? null,

                'phone'      => $user->phone ?? null,
                'created_at' => $user->created_at,
            ],

            'wallet' => $wallet ? [
                'id'                       => $wallet->id,
                'currency'                 => $wallet->currency,
                'symbol'                   => $wallet->symbol,


                'balance'                  => (float) ($wallet->balance ?? 0),


                'balance_withdrawal'       => (float) ($wallet->balance_withdrawal ?? 0),


                'balance_deposit_rollover' => (float) ($wallet->balance_deposit_rollover ?? 0),


                'balance_bonus'            => (float) ($wallet->balance_bonus ?? 0),


                'balance_bonus_rollover'   => (float) ($wallet->balance_bonus_rollover ?? 0),


                'refer_rewards'            => (float) ($wallet->refer_rewards ?? 0),

                // V15.3: saldo consolidado usado pela Home e pelo perfil.
                'real_balance'             => round((float) ($wallet->balance ?? 0) + (float) ($wallet->balance_withdrawal ?? 0), 2),
                'total_balance'            => (float) $wallet->total_balance,
            ] : null,

            'stats' => [
                'total_earnings' => (float) $totalEarnings,
                'total_bets'     => (int)   $totalBetsCount,
                'sum_bets'       => (float) $sumBets,
            ],

            'limits' => [
                'deposit'    => $depositLimits,
                'withdrawal' => $withdrawalLimits,
            ],

            'bonus'      => $bonus,
            'affiliate'  => $affiliate,

            'last_bets'        => $lastBets,
            'last_deposits'    => $lastDeposits,
            'last_withdrawals' => $lastWithdrawals,
        ]);
    }


    public function bets(Request $request)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $type    = $request->get('type', 'all'); 
        $perPage = (int) $request->get('per_page', 15);

        if ($perPage < 1) {
            $perPage = 15;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }


        $query = Order::with('gameDetails')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($type === 'bet') {
            $query->where('type', 'bet');
        } elseif ($type === 'win') {
            $query->where('type', 'win');
        }

        $bets = $query->paginate($perPage);


        $baseQuery = Order::where('user_id', $user->id);

        $stats = [
            'total_earnings' => (float) (clone $baseQuery)
                ->where('type', 'win')
                ->sum('amount'),
            'total_bets'     => (int) (clone $baseQuery)
                ->where('type', 'bet')
                ->count(),
            'sum_bets'       => (float) (clone $baseQuery)
                ->where('type', 'bet')
                ->sum('amount'),
        ];


        $bets->setCollection(
            $bets->getCollection()->map(function ($order) {
                return [
                    'id'         => $order->id,
                    'type'       => $order->type, 
                    'amount'     => (float) $order->amount,
                    'status'     => $order->status ?? null,
                    'created_at' => $order->created_at,


                    'game_code'  => $order->game,


                    'game_name'  => optional($order->gameDetails)->game_name ?? (string) $order->game,
                ];
            })
        );

        return response()->json([
            'status' => true,
            'user'   => [
                'id'   => $user->id,
                'name' => $user->name,
            ],
            'stats' => $stats,
            'bets'  => $bets,
        ]);
    }


    public function transactions(Request $request)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $type    = $request->get('type', 'all'); 
        $perPage = (int) $request->get('per_page', 10);


        if ($perPage < 1) {
            $perPage = 10;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }


        $userId = $user->id;


        $totalDeposits    = (float) Deposit::where('user_id', $userId)->sum('amount');
        $totalWithdrawals = (float) Withdrawal::where('user_id', $userId)->sum('amount');
        $totalTransactions = (int) (
            Deposit::where('user_id', $userId)->count()
            + Withdrawal::where('user_id', $userId)->count()
        );

        $stats = [
            'total_deposits'      => $totalDeposits,
            'total_withdrawals'   => $totalWithdrawals,
            'total_transactions'  => $totalTransactions,
        ];





        $depositQuery = Deposit::select([
                'id',
                'amount',
                'status',
                'created_at',
                DB::raw("'deposit' as type"),
            ])
            ->where('user_id', $userId);

        $withdrawalQuery = Withdrawal::select([
                'id',
                'amount',
                'status',
                'created_at',
                DB::raw("'withdrawal' as type"),
            ])
            ->where('user_id', $userId);

        if ($type === 'deposit') {

            $base = $depositQuery;
        } elseif ($type === 'withdrawal') {

            $base = $withdrawalQuery;
        } else {

            $union = $depositQuery->unionAll($withdrawalQuery);
            $base = DB::query()->fromSub($union, 't');
        }


        $transactions = $base
            ->orderByDesc('created_at')
            ->paginate($perPage);


        $transactions->setCollection(
            $transactions->getCollection()->map(function ($tx) {

                $direction = $tx->type === 'deposit' ? 'credit' : 'debit';

                return [
                    'id'         => $tx->id,
                    'type'       => $tx->type, 
                    'amount'     => (float) $tx->amount,
                    'status'     => $tx->status ?? null,
                    'created_at' => $tx->created_at,



                    'method'     => null,


                    'direction'  => $direction,
                ];
            })
        );

        return response()->json([
            'status'       => true,
            'user'         => [
                'id'   => $user->id,
                'name' => $user->name,
            ],
            'stats'        => $stats,
            'transactions' => $transactions,
        ]);
    }
}
