<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HouseGame extends Model
{
    use HasFactory;

    protected $table = 'house_games';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'cover',
        'icon',
        'engine_path',
        'active',
        'show_home',
        'sort_order',
        'views',
        'min_bet',
        'max_bet',
        'coin_rate',
        'meta_multiplier',
        'max_win_multiplier',
        'player_speed',
        'engine_params',
        'min_win_seconds',
        'round_timeout_seconds',
    ];

    protected $casts = [
        'active' => 'boolean',
        'show_home' => 'boolean',
        'sort_order' => 'integer',
        'views' => 'integer',
        'min_bet' => 'decimal:2',
        'max_bet' => 'decimal:2',
        'coin_rate' => 'decimal:6',
        'meta_multiplier' => 'decimal:4',
        'max_win_multiplier' => 'decimal:4',
        'player_speed' => 'decimal:4',
        'engine_params' => 'array',
        'min_win_seconds' => 'integer',
        'round_timeout_seconds' => 'integer',
    ];

    public function rounds(): HasMany
    {
        return $this->hasMany(HouseGameRound::class, 'house_game_id');
    }

    public function engineSettings(): array
    {
        return array_merge([
            'coin_rate' => (float) $this->coin_rate,
            'coin_multiplier' => (float) $this->coin_rate,
            'meta_multiplier' => (float) $this->meta_multiplier,
            'player_speed' => (float) $this->player_speed,
        ], $this->engine_params ?? []);
    }
}
