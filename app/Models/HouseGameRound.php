<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseGameRound extends Model
{
    use HasFactory;

    protected $table = 'house_game_rounds';

    public const STATUS_OPENING = 'opening';
    public const STATUS_OPEN = 'open';
    public const STATUS_SETTLING = 'settling';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_PAYOUT_FAILED = 'payout_failed';

    protected $fillable = [
        'round_uuid',
        'client_event_id',
        'user_id',
        'house_game_id',
        'game_slug',
        'bet',
        'meta_amount',
        'max_payout',
        'client_claim',
        'payout',
        'status',
        'type_money',
        'debits',
        'rollover_before_bet',
        'engine_token_hash',
        'opened_at',
        'expires_at',
        'launched_at',
        'settled_at',
        'failure_reason',
    ];

    protected $casts = [
        'bet' => 'decimal:2',
        'meta_amount' => 'decimal:2',
        'max_payout' => 'decimal:2',
        'client_claim' => 'decimal:2',
        'payout' => 'decimal:2',
        'debits' => 'array',
        'rollover_before_bet' => 'array',
        'opened_at' => 'datetime',
        'expires_at' => 'datetime',
        'launched_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(HouseGame::class, 'house_game_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
