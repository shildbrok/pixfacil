<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerArcadeVisit extends Model
{
    protected $fillable = [
        'user_id', 'game_slug', 'visits', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'visits' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
