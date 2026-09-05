<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Banner extends Model
{
    use HasFactory;

    protected $table = 'banners';

    protected $fillable = [
        'image',
        'type',
        'description',
        'link',
        'is_active',
        'sort_order',
        'show_desktop',
        'show_mobile',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_desktop' => 'boolean',
        'show_mobile' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        $flush = static function (): void {
            Cache::forget('api:banners:index:v1');
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
