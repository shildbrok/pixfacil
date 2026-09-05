<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log cru de webhook, de qualquer gateway.
 *
 * Guardar o payload como chegou é trilha de auditoria (cassino) e é o que
 * permite reprocessar um evento à mão. Registra inclusive os descartados.
 */
class GatewayWebhookLog extends Model
{
    protected $table = 'gateway_webhook_logs';

    protected $fillable = [
        'gateway', 'channel', 'provider_id', 'external_id', 'ip', 'payload', 'processed_at', 'error',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];
}
