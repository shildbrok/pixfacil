<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User; 

class DailyBonusClaim extends Model
{
    protected $table = 'daily_bonus_claims';
    public $timestamps = false;

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'claimed_at',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}