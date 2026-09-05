<?php



namespace App\Http\Controllers\Api\BetCrm;

use App\Http\Controllers\Controller;
use App\Models\BetcrmSetting;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\BetCrm\BetCrmService;
use Illuminate\Http\Request;

class BetCrmController extends Controller
{

    private const BETCRM_IPS = ['140.99.164.26'];


    public function leadStats(Request $request)
    {


        if (! $this->isFromBetCrm($request)) {
            return response()->json(['message' => 'FORBIDDEN'], 403);
        }

        $bcRef      = trim((string) $request->query('bc_ref', ''));
        $externalId = trim((string) $request->query('external_id', ''));


        $user = $bcRef !== ''
            ? User::query()->where('betcrm_ref', $bcRef)->first()
            : null;

        if (! $user && $externalId !== '' && ctype_digit($externalId)) {
            $user = User::query()->find((int) $externalId);
        }

        if (! $user) {
            return response()->json(['message' => 'LEAD_NOT_FOUND'], 404);
        }

        $deposits = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->sum('price');

        $withdrawals = (float) Withdrawal::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [1, 9])
            ->sum('amount');

        $bets = (float) Order::query()
            ->where('user_id', $user->id)
            ->where('type', 'bet')
            ->sum('amount');

        return response()->json([
            'total_bets'        => round($bets, 2),
            'total_deposits'    => round($deposits, 2),
            'total_withdrawals' => round($withdrawals, 2),
            'currency'          => 'BRL',
        ]);
    }


    private function isFromBetCrm(Request $request): bool
    {
        $configured = optional(BetcrmSetting::query()->find(1))->callback_ips;

        $allowed = filled($configured)
            ? array_filter(array_map('trim', explode(',', (string) $configured)))
            : self::BETCRM_IPS;

        return in_array((string) $request->ip(), $allowed, true);
    }


    public function appInstall(Request $request)
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'UNAUTHENTICATED'], 401);
        }

        $platform = (string) ($request->input('platform') ?: 'pwa');
        BetCrmService::sendAppInstall($user, $platform);

        return response()->json(['message' => 'OK']);
    }
}