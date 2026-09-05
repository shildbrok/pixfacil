<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeColorTokenSource extends Model
{
    use HasFactory;

    protected $table = 'theme_color_token_sources';

    protected $fillable = [
        'system_color_id',
        'theme_color_token_id',
        'raw_token',
        'raw_color_value',
        'canonical_token',
        'canonical_value',
        'usage_count',
    ];

    protected $casts = [
        'system_color_id' => 'integer',
        'theme_color_token_id' => 'integer',
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function systemColor(): BelongsTo
    {
        return $this->belongsTo(SystemColor::class, 'system_color_id');
    }

    public function themeColorToken(): BelongsTo
    {
        return $this->belongsTo(ThemeColorToken::class, 'theme_color_token_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('usage_count')->orderBy('id');
    }
}