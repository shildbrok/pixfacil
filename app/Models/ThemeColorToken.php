<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThemeColorToken extends Model
{
    use HasFactory;

    protected $table = 'theme_color_tokens';

    protected $fillable = [
        'token',
        'label',
        'family',
        'group_name',
        'color_value',
        'color_format',
        'css_variable',
        'is_editable',
        'is_active',
        'sort_order',
        'total_sources',
        'total_occurrences',
        'notes',
    ];

    protected $casts = [
        'is_editable' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'total_sources' => 'integer',
        'total_occurrences' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sources(): HasMany
    {
        return $this->hasMany(ThemeColorTokenSource::class, 'theme_color_token_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEditable($query)
    {
        return $query->where('is_editable', true);
    }

    public function scopeEssentials($query)
    {
        return $query->whereIn('token', [
            'primary',
            'primary-soft',
            'background',
            'surface',
            'surface-2',
            'text',
            'text-muted',
            'success',
            'warning',
            'danger',
            'info',
            'accent',
            'black',
            'transparent',
        ]);
    }

    public function scopeDerived($query)
    {
        return $query->whereNotIn('token', [
            'primary',
            'primary-soft',
            'background',
            'surface',
            'surface-2',
            'text',
            'text-muted',
            'success',
            'warning',
            'danger',
            'info',
            'accent',
            'black',
            'transparent',
        ]);
    }

    public function scopeOrdered($query)
    {
        return $query
            ->orderBy('sort_order')
            ->orderByDesc('total_occurrences')
            ->orderBy('token');
    }

    public function getPreviewStyleAttribute(): string
    {
        return "background: {$this->color_value};";
    }
}