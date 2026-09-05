<?php



namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{

    protected $except = [

        'betcrm_ref',
        'betcrm_utm_source',
        'betcrm_utm_medium',
        'betcrm_utm_campaign',
        'betcrm_fbclid',
        'betcrm_gclid',
    ];
}