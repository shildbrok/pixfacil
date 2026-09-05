<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CustomLayout extends Model
{
    use HasFactory;


    protected static function booted(): void
    {
        $flush = fn () => \App\Helpers\Core::forgetSetting();
        static::saved($flush);
        static::deleted($flush);
    }


    protected $table = 'custom_layouts';



    protected $fillable = [


        'link_app',
        'link_telegram',
        'link_facebook',
        'link_whatsapp',
        'link_instagram',
        'link_lincenca',

        'footer_imagen1',
        'footer_imagen2',
        'footer_imagen3',
        'footer_imagen4',
        




    
   
        'banner_registro',
        'banner_login',

        
        'link_suporte',
       
        
        'token_jivochat',


        'maiores_ganhos_status',
        'live_ganhos_status',
        'baixar_app_imagem',
    ];



    public static function getCachedCustomLayout()
    {

        $cacheTime = 30 * 60; 

        return Cache::remember('custom_layout', $cacheTime, function () {

            return self::first();
        });
    }

}