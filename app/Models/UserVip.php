<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vip_id',
        'last_reward_claimed_at',
    ];

    /**
     * Sem este cast o Eloquent devolve last_reward_claimed_at como STRING (só
     * created_at/updated_at vêm convertidos de graça), e qualquer método de data
     * chamado sobre ela é erro fatal. O resgate de VIP faz exatamente isso.
     */
    protected $casts = [
        'last_reward_claimed_at' => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function vip()
    {
        return $this->belongsTo(Vip::class);
    }
}
