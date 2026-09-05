<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HomeSection extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'type', 'category_id', 'icon', 'games_limit', 'sort_order', 'active',
    ];

    protected $casts = [
        'active'      => 'boolean',
        'games_limit' => 'integer',
        'sort_order'  => 'integer',
        'category_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /** Jogos escolhidos a dedo (type=manual). */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'home_section_game', 'home_section_id', 'game_id')
            ->withPivot('sort_order')
            ->orderBy('home_section_game.sort_order');
    }
}
