<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Provider extends Model
{
    use HasFactory;


    protected $table = 'providers';


    protected $fillable = [
        'code',
        'name',
        'rtp',
        'cover',
        'pixfacil_home_cover',
        'status',
        'distribution',
        'views',
        'sort_order',
    ];

    protected static function booted(): void
    {
        $clear = static function (): void {
            Cache::forget('home:providers:v1');
            Cache::forget('home:providers:v2');
            Cache::forget('home:providers:v3');
        };
        static::saved($clear);
        static::deleted($clear);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'provider_id', 'id')
            ->orderBy('views', 'desc')
            ->where('show_home', 1)
            ;
    }

}
