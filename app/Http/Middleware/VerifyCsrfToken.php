<?php



namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{

    protected $except = [
        'api/*',
        'gerapix/*',
        'digitopay/*',
        'pixup/*',
        'podpay/*',
        'forceonepay/*',
        'webhooks/*',
        'cron/*',
        'playfiver/*',
        '/cores'  
    ];
}