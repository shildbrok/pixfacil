<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerAchievement extends Model
{
    protected $fillable = ['user_id', 'code', 'xp', 'unlocked_at'];

    protected $casts = [
        'xp' => 'integer',
        'unlocked_at' => 'datetime',
    ];
}
