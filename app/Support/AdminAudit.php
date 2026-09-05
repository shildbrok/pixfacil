<?php

namespace App\Support;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * C-02: registra acoes administrativas sensiveis numa trilha imutavel.
 *
 * Uso tipico:
 *   AdminAudit::log('wallet.edit', $wallet, $before, $after, 'Editou saldos', $wallet->user_id);
 *
 * Nunca lanca excecao para o chamador — se o log falhar, apenas registra no laravel.log,
 * para jamais bloquear a acao administrativa em si.
 */
class AdminAudit
{
    public static function log(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $description = null,
        ?int $targetUserId = null
    ): void {
        try {
            $admin = auth()->user();

            AdminAuditLog::create([
                'admin_id'       => $admin?->id,
                'admin_name'     => $admin?->name,
                'action'         => $action,
                'subject_type'   => $subject ? get_class($subject) : null,
                'subject_id'     => $subject?->getKey(),
                'target_user_id' => $targetUserId,
                'description'    => $description,
                'before'         => $before ?: null,
                'after'          => $after ?: null,
                'ip'             => request()->ip(),
                'user_agent'     => (string) request()->userAgent(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[AdminAudit] falha ao registrar acao administrativa', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
