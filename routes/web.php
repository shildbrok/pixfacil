<?php

use App\Http\Controllers\Api\Games\GameController;
use App\Http\Controllers\Api\Profile\WalletController;
use App\Http\Controllers\Api\Settings\BrandingController;
use App\Http\Controllers\Assets\PixFacilRuntimeController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CacheController;
use App\Http\Controllers\Admin\KycFileController;
use App\Http\Controllers\Admin\DepositProofController;
use App\Http\Controllers\Gateway\GeraPixController;
use App\Http\Controllers\Layouts\ApplicationController;

Route::middleware(['auth', 'can:admin', 'throttle:api'])->group(function () {
    Route::get('/admin/kyc/file/{verificacao}/{type}', [KycFileController::class, 'show'])->name('admin.kyc.file');
    Route::get('/admin/deposits/proof/{deposit}', [DepositProofController::class, 'show'])->name('admin.deposits.proof');
    Route::post('/withdrawal/{id}', [WalletController::class, 'withdrawalFromModal'])->name('withdrawal');
    Route::post('/withdrawal/{id}/cancel', [WalletController::class, 'cancelWithdrawalFromModal'])->name('withdrawal.cancel');
    Route::post('/admin/cache/nuke', [CacheController::class, 'nuke'])->name('admin.cache.nuke');

    Route::post('/update-colors', function () {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        return back()->with('status', 'Cores atualizadas com sucesso!');
    })->name('update.colors');

    Route::post('/clear-memory', function () {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        return back()->with('status', 'Memória limpa com sucesso!');
    })->name('clear.memory');

    Route::post('/optimize-system', function () {
        Artisan::call('optimize');
        return back()->with('status', 'Sistema otimizado com sucesso!');
    })->name('optimize.system');
});

Route::get('/branding/data', BrandingController::class)
    ->middleware('throttle:settings');

Route::get('/pixfacil/runtime.js', PixFacilRuntimeController::class)
    ->name('pixfacil.runtime');

Route::post('/playfiver/webhook', [GameController::class, 'webhookPlayFiver'])
    ->middleware('throttle:webhooks');

Route::post('/gerapix/callback', [GeraPixController::class, 'callback'])
    ->middleware('throttle:webhooks');

Route::middleware('throttle:webhooks')->group(function () {
    Route::post('/digitopay/callback/cashin', [\App\Http\Controllers\Gateway\DigitoPayController::class, 'handle'])
        ->defaults('channel', 'cashin');
    Route::post('/digitopay/callback/cashout', [\App\Http\Controllers\Gateway\DigitoPayController::class, 'handle'])
        ->defaults('channel', 'cashout');
});

Route::post('/pixup/callback', [\App\Http\Controllers\Gateway\PixupController::class, 'callback'])
    ->middleware('throttle:webhooks');
Route::post('/podpay/callback', [\App\Http\Controllers\Gateway\PodPayController::class, 'callback'])
    ->middleware('throttle:webhooks');

Route::middleware('throttle:webhooks')->group(function () {
    Route::post('/forceonepay/callback/wd/{uuid}', [\App\Http\Controllers\Gateway\ForceOnePayController::class, 'cashOut']);
    Route::post('/forceonepay/callback/{secret}', [\App\Http\Controllers\Gateway\ForceOnePayController::class, 'cashIn']);
});

Route::get('{view}', ApplicationController::class)->where('view', '^(?!api/).*$')
    ->middleware(\App\Http\Middleware\CaptureBetCrmRef::class);
