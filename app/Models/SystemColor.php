<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SystemColor extends Model
{
    use HasFactory;

    protected $table = 'system_colors';

    protected $fillable = [
        'token',
        'family',
        'group_name',
        'canonical_token',
        'color_value',
        'canonical_value',
        'css_variable',
        'source_type',
        'mapped_at',
        'color_format',
        'usage_count',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'mapped_at' => 'datetime',
        'usage_count' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function themeColorTokenSource(): HasOne
    {
        return $this->hasOne(ThemeColorTokenSource::class, 'system_color_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('usage_count')->orderBy('id');
    }

    public function getIsMappedAttribute(): bool
    {
        return ! empty($this->canonical_token);
    }
}