<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Mission extends Model
{
    use HasFactory;


    protected $fillable = [
        'title',
        'description',
        'type',
        'game_id',
        'target_amount',
        'reward',
        'image',
        'status',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            self::clearMissionCaches();
        });

        static::deleted(function () {
            self::clearMissionCaches();
        });
    }


    public static function clearMissionCaches(): void
    {
        Cache::forget('missions:v1:active:list');
    }


    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id');
    }


    public function users()
    {
        return $this->hasMany(MissionUser::class, 'mission_id');
    }


    public function isBetMission(): bool
    {
        return in_array($this->type, [
            'game_bet',
            'total_bet',
            'rounds_played',
            'win_amount',
            'loss_amount',
        ], true);
    }


    public function isDepositMission(): bool
    {
        return $this->type === 'deposit';
    }
}