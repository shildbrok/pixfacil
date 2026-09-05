<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;


    protected static function booted(): void
    {
        $flush = fn () => \App\Helpers\Core::forgetSetting();
        static::saved($flush);
        static::deleted($flush);
    }


    protected $table = 'settings';


    protected $fillable = [
        'software_name',


        'software_favicon',
        'software_logo_white',
        'software_logo_black',
        'pixfacil_mobile_logo',
        'pixfacil_mobile_banner',
        'pixfacil_loading_logo',

        'storage',
        'min_deposit',
        'max_deposit',
        'min_withdrawal',
        'max_withdrawal',



      

        'revshare_reverse',


        'initial_bonus',


        'rollover',
        'rollover_deposit',
        'disable_rollover',

        'gerapix_is_enable',
        // Sem estar aqui, o update() ignora a flag em silêncio e o toggle do
        // admin nunca salva.
        'digitopay_is_enable',
        'pixup_is_enable',
        'podpay_is_enable',
        'abilitypay_is_enable',
        'forceonepay_is_enable',
        'deposit_gateway',
        'saque',


        'cpa_value',
        'cpa_baseline',


        'withdrawal_limit',
        'withdrawal_period',
        

        'withdrawal_auto_approve',
        'withdrawal_auto_approve_max',

        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
       
        'twitter_title',
        'twitter_description',
        'allow_indexing',
        'site_url',

    ];

    protected $hidden = array('updated_at');
}
