<?php



namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\KycConfig;
use App\Models\Verificacao;
use Illuminate\Http\JsonResponse;

class VerificationStatusController extends Controller
{

    public function __invoke(): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'error' => 'Não autenticado.',
            ], 401);
        }

        $kycConfig = KycConfig::current();

        $kyc = Verificacao::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $status = $kyc?->status ?? 'none';

        $withdrawalRequired = $kycConfig->isWithdrawalRequired();

        return response()->json([
            'kyc_required_for_withdrawal' => $withdrawalRequired,
            'auto_approve_documents' => $kycConfig->isAutoApproveDocumentsEnabled(),
            'kyc_config_active' => (bool) $kycConfig->is_active,

            'status' => $status,
            'has_submission' => (bool) $kyc,
            'is_approved' => $status === Verificacao::STATUS_APPROVED,
            'is_pending' => $status === Verificacao::STATUS_PENDING,
            'is_rejected' => $status === Verificacao::STATUS_REJECTED,

            'submitted_at' => optional($kyc?->created_at)->toDateTimeString(),
            'approved_at' => optional($kyc?->aprovado_em)->toDateTimeString(),
            'rejection_reason' => $kyc?->observacao_rejeicao,

            'can_withdraw' => ! $withdrawalRequired
                || $status === Verificacao::STATUS_APPROVED,
        ], 200);
    }
}