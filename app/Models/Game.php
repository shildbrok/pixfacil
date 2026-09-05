<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Game extends Model
{
    use HasFactory;

    protected $table = 'games';
    protected $primaryKey = 'id';

    protected $fillable = [
        'provider_id',
        'game_server_url',
        'game_id',
        'game_name',
        'game_code',
        'game_type',
        'description',
        'cover',
        'status',
        'technology',
        'has_lobby',
        'is_mobile',
        'has_freespins',
        'has_tables',
        'only_demo',
        'rtp',
        'distribution',
        'views',
        'is_featured',
        'show_home',
        'original',
    ];


    protected $casts = [
        'status'        => 'boolean',
        'has_lobby'     => 'boolean',
        'is_mobile'     => 'boolean',
        'has_freespins' => 'boolean',
        'has_tables'    => 'boolean',
        'only_demo'     => 'boolean',
        'is_featured'   => 'boolean',
        'show_home'     => 'boolean',
        'original'      => 'boolean',

        'views' => 'integer',
        'rtp'   => 'integer',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    protected $appends = [
        'date_human_readable',
        'created_at_formatted',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            self::clearCatalogCaches();
        });

        static::deleted(function () {
            self::clearCatalogCaches();
        });
    }


    public static function clearCatalogCaches(): void
    {
        Cache::forget('pf:v6:providers_with_games_priority_min');
        Cache::forget('pf:v2:games_by_categories');
        Cache::forget('pf:v2:featured_games:min');

        Cache::forget('pf:v7:providers_with_games_priority_min');
        Cache::forget('pf:v3:games_by_categories');
        Cache::forget('pf:v3:featured_games:min');

        try {
            if (! Cache::has('games_cache_version')) {
                Cache::forever('games_cache_version', 1);
            }

            Cache::increment('games_cache_version');
        } catch (\Throwable $e) {
            $current = (int) Cache::get('games_cache_version', 1);
            Cache::forever('games_cache_version', $current + 1);
        }
    }



    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id', 'id');
    }

    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_game', 'game_id', 'category_id');
    }



    public function getCreatedAtFormattedAttribute(): ?string
    {
        return $this->created_at?->format('Y-m-d');
    }

    public function getDateHumanReadableAttribute(): ?string
    {
        return $this->created_at?->diffForHumans();
    }


    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:sP');
    }



    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', 1);
    }


    public function scopeSearch($query, ?string $term)
    {
        if (! $term || mb_strlen($term) < 3) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('game_code', 'like', "%{$term}%")
              ->orWhere('game_name', 'like', "%{$term}%")
              ->orWhere('distribution', 'like', "%{$term}%")
              ->orWhereHas('provider', function ($p) use ($term) {
                  $p->where('name', 'like', "%{$term}%");
              });
        });
    }
}