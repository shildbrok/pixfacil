<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;


    protected $table = 'wallets';
    protected $appends = ['total_balance'];


    protected $casts = [
        'balance'                  => 'decimal:2',
        'balance_withdrawal'       => 'decimal:2',
        'balance_bonus'            => 'decimal:2',
        'balance_deposit_rollover' => 'decimal:2',
        'balance_bonus_rollover'   => 'decimal:2',
        'refer_rewards'            => 'decimal:2',
        'welcome_bonus_at'         => 'datetime',
    ];


    protected $fillable = [
        'user_id',
        'currency',
        'symbol',
        'balance',
        'balance_withdrawal',
        'balance_deposit_rollover',
        'balance_bonus',
        'balance_bonus_rollover',
        'refer_rewards',
        'balance_cryptocurrency',
        'balance_demo',
        'total_bet',
        'total_won',
        'total_lose',
        'last_won',
        'last_lose',
        'hide_balance',
        'active',
        'getaway',
        'welcome_bonus_at',
    ];


    public function getTotalBalanceAttribute(): float
    {
        return round((float) $this->balance + (float) $this->balance_bonus + (float) $this->balance_withdrawal, 2);
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
