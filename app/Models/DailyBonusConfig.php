<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyBonusConfig extends Model
{

    protected $table = 'daily_bonus_configs';


    protected $fillable = [
        'bonus_value',
        'cycle_hours',
    ];




}
