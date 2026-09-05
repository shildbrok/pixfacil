<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AggregatorWallet extends Model
{
    use HasFactory;

    public const PRIMARY_BLOCK_THRESHOLD = 80.00;

    protected $table = 'aggregator_wallets';

    protected $fillable = [
        'provider',
        'wallet_key',
        'name',
        'balance',
        'currency',
        'is_primary',
        'info_only',
        'notify_enabled',
        'notify_email',
        'notify_threshold',
        'was_below_notify_threshold',
        'last_notified_at',
        'last_synced_at',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'notify_threshold' => 'decimal:2',
        'is_primary' => 'boolean',
        'info_only' => 'boolean',
        'notify_enabled' => 'boolean',
        'was_below_notify_threshold' => 'boolean',
        'last_notified_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public function fixedBlockThreshold(): ?float
    {
        return $this->is_primary ? self::PRIMARY_BLOCK_THRESHOLD : null;
    }

    public function isBlockingNow(): bool
    {
        if (! $this->is_primary) {
            return false;
        }

        return (float) $this->balance < self::PRIMARY_BLOCK_THRESHOLD;
    }

    public function isBelowNotificationThreshold(): bool
    {
        if (! $this->notify_enabled || $this->notify_threshold === null) {
            return false;
        }

        return (float) $this->balance < (float) $this->notify_threshold;
    }
}