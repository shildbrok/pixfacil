<?php



namespace App\Http\Middleware;

use App\Services\PlayFiverBalanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAggregatorAccess
{
    public function __construct(private readonly PlayFiverBalanceService $service)
    {
    }

    public function handle(Request $request, Closure $next, string $action = 'general'): Response
    {
        $gate = $this->service->gateForAction($action);

        if (! ($gate['allowed'] ?? false)) {
            return response()->json([
                'error' => $gate['message'],
                'blocked' => true,
                'action' => $action,
                'details' => $gate['details'] ?? null,
            ], $gate['status_code'] ?? 423);
        }

        return $next($request);
    }
}
