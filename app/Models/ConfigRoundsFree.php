<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigRoundsFree extends Model
{
    use HasFactory;

    protected $table = 'configs_rounds_free';

    protected $fillable = [
        'spins',
        'game_code',
        'value',
        'status',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'status' => 'boolean',
        'spins' => 'integer',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class, 'game_code', 'game_code');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function getGameNameAttribute(): string
    {
        return $this->game?->game_name ?: $this->game_code;
    }
}