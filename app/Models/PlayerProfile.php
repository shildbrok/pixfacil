<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerProfile extends Model
{
    protected $fillable = [
        'user_id', 'nickname', 'avatar_key', 'frame_key',
        'leaderboard_opt_in', 'arcade_xp',
    ];

    protected $casts = [
        'leaderboard_opt_in' => 'boolean',
        'arcade_xp' => 'integer',
    ];
}
