<?php



use App\Http\Controllers\Api\Profile\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Games\GameController;
use App\Models\Promocao;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\VipController;
use App\Http\Controllers\Api\DailyBonusController;
use App\Http\Controllers\Api\GameOpenController;
use App\Http\Controllers\Api\Profile\WalletController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Games\GameSessionController;
use App\Http\Controllers\Api\Profile\AccountController;
use App\Http\Controllers\Api\Profile\VerificationController;
use App\Http\Controllers\Api\Profile\PixKeyController;
use App\Http\Controllers\Api\Profile\VerificationStatusController;

use App\Http\Controllers\Api\Categories\CategoryController;
use App\Http\Controllers\Api\Settings\BannerController;
use App\Http\Controllers\Api\Profile\AffiliateController;
use App\Http\Controllers\Api\Profile\RecentsController;
use App\Http\Controllers\Api\Wallet\DepositController;
use App\Http\Controllers\Api\Wallet\WithdrawController;
use App\Http\Controllers\Api\Search\SearchGameController;
use App\Http\Controllers\Api\Settings\SettingController;
use App\Http\Controllers\Api\RoundsFreeConfigController;
use App\Http\Controllers\Api\Retro\RetroGameController;
use App\Http\Controllers\Api\Retro\RetroEngineController;
use App\Http\Controllers\Api\PlayerExperience\PlayerExperienceController;


Route::group(['middleware' => ['auth.jwt', 'check.session']], function () {


    Route::post('profile/select-avatar', [ProfileController::class, 'selectAvatar'])
        ->name('profile.select-avatar')
        ->middleware('throttle:api');


    Route::post('carteira_wallet/withdraw/request', [WalletController::class, 'requestWithdrawal'])
        ->name('wallet.withdraw.request')
        ->middleware('throttle:wallet');


    Route::post('games/session/start', [GameSessionController::class, 'start'])
        ->name('games.session.start')
        ->middleware(['throttle:games', 'aggregator.access:game']);

    Route::post('games/session/ping', [GameSessionController::class, 'ping'])
        ->name('games.session.ping')
        ->middleware('throttle:games');

    Route::post('games/session/close', [GameSessionController::class, 'close'])
        ->name('games.session.close')
        ->middleware('throttle:games');


    Route::get('profile/pix-keys', [PixKeyController::class, 'index'])
        ->name('profile.pix-keys.index')
        ->middleware('throttle:wallet');

    Route::post('profile/pix-keys', [PixKeyController::class, 'store'])
        ->name('profile.pix-keys.store')
        ->middleware('throttle:wallet');

    Route::delete('profile/pix-keys/{id}', [PixKeyController::class, 'destroy'])
        ->name('profile.pix-keys.destroy')
        ->middleware('throttle:wallet');


    Route::get('profile/verification/status', VerificationStatusController::class)
        ->name('profile.verification.status')
        ->middleware('throttle:api');


    Route::get('/profile/account', [AccountController::class, 'overview'])
        ->middleware('throttle:api');

    Route::get('/profile/bets', [AccountController::class, 'bets'])
        ->middleware('throttle:api');

    Route::get('/profile/transactions', [AccountController::class, 'transactions'])
        ->middleware('throttle:api');

    Route::get('/profile/verification', [VerificationController::class, 'show'])
        ->middleware('throttle:api');

    Route::post('/profile/verification', [VerificationController::class, 'store'])
        ->middleware('throttle:kyc');
});


Route::get('/promocoes', function () {

    $promocoes = \App\Models\Promocao::orderByDesc('id')->limit(50)->get()->map(function ($promo) {

        if (! empty($promo->imagem)) {
            $v = optional($promo->updated_at)->getTimestamp() ?: optional($promo->created_at)->getTimestamp();
            if ($v) {
                $promo->imagem .= (str_contains($promo->imagem, '?') ? '&' : '?') . 'v=' . $v;
            }
        }

        return $promo;
    });

    return response()->json($promocoes)
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
})->middleware('throttle:public-short');


Route::prefix('jogos')->group(function () {
    Route::get('/lista', [GameController::class, 'listarTodos'])
        ->middleware('throttle:games');

    Route::get('/procurar', [GameController::class, 'buscarPorNome'])
        ->middleware('throttle:games');

    Route::get('/categorias', [GameController::class, 'buscarPorCategoria'])
        ->middleware('throttle:games');

    Route::get('/provedora', [GameController::class, 'buscarPorProvedora'])
        ->middleware('throttle:games');
});


Route::group(['middleware' => ['auth.jwt', 'check.session']], function () {


    Route::prefix('daily-bonus')->group(function () {
        Route::get('/check', [DailyBonusController::class, 'check'])
            ->middleware('throttle:api');
        Route::post('/claim', [DailyBonusController::class, 'claim'])
            ->middleware('throttle:api');
    });

    Route::get('/games/open-check', [GameOpenController::class, 'checkDailyDeposit'])
        ->middleware(['throttle:api']);
});


Route::group(['prefix' => 'auth', 'as' => 'auth.'], function () {


    Route::group(['middleware' => ['throttle:auth']], function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('aggregator.access:login');
        Route::post('register', [AuthController::class, 'register'])->middleware('aggregator.access:register');

        Route::post('forget-password', [AuthController::class, 'submitForgetPassword']);
        Route::post('reset-password/{token}', [AuthController::class, 'submitResetPassword']);
    });


    Route::group(['middleware' => ['auth.jwt', 'check.session', 'throttle:api']], function () {
        Route::get('verify', [AuthController::class, 'verify']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});


Route::group(['middleware' => ['auth.jwt', 'check.session', 'throttle:api']], function () {


    Route::prefix('missions')->group(function () {
        Route::get('/', [MissionController::class, 'index']);
        Route::post('/{missionId}/progress', [MissionController::class, 'updateProgress']);
        Route::post('/{missionId}/redeem', [MissionController::class, 'redeemReward']);
        Route::get('/{missionId}/check', [MissionController::class, 'checkIfRedeemed']);
    });


    Route::prefix('vips')->group(function () {
        Route::get('/', [VipController::class, 'getVipsWithProgress']);
        Route::post('/{vipId}/claim', [VipController::class, 'claimVipReward']);
    });


    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'index']);

        Route::middleware('throttle:affiliate')->group(function () {
            Route::get('affiliates/', [AffiliateController::class, 'index']);
            Route::get('affiliates/generate', [AffiliateController::class, 'generateCode']);
            Route::post('affiliates/request', [AffiliateController::class, 'makeRequest']);
        });

        Route::get('wallet/', [WalletController::class, 'index']);
        Route::get('mywallet/', [WalletController::class, 'myWallet']);
        Route::post('mywallet/{id}', [WalletController::class, 'setWalletActive']);

        Route::get('recents/', [RecentsController::class, 'index']);
        // M-04: rota removida — VipController::index nao existe (dava 500). Front usa /api/vips.
    });


    Route::prefix('carteira_wallet')->group(function () {


        Route::get('/deposit/rounds-free-configs', [RoundsFreeConfigController::class, 'index'])
            ->name('wallet.deposit.rounds-free-configs')
            ->middleware('throttle:wallet');


        Route::get('/deposit', [DepositController::class, 'index'])
            ->name('wallet.deposit.index')
            ->middleware('throttle:wallet');

        Route::post('/deposit/payment', [DepositController::class, 'submitPayment'])
            ->name('wallet.deposit.payment')
            ->middleware('throttle:wallet');

        Route::post('/deposit/status', [DepositController::class, 'consultStatusTransactionPix'])
            ->name('wallet.deposit.status')
            ->middleware('throttle:wallet');


        Route::get('withdraw/', [WithdrawController::class, 'index'])
            ->middleware('throttle:wallet');



    });
});


Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])
        ->middleware('throttle:public-short');
});


Route::get('/games/all', [GameController::class, 'index'])
    ->middleware('throttle:games');

Route::get('/games/single/{id}', [GameController::class, 'show'])
    ->middleware(['auth.jwt', 'check.session', 'throttle:games', 'aggregator.access:game']);

Route::any('/featured/games', [GameController::class, 'featured'])
    ->middleware('throttle:games');

Route::get('/casinos/games', [GameController::class, 'allGames'])
    ->middleware('throttle:games');


// Home enxuta: seções dinâmicas (geridas no admin) com só os campos de exibição.
Route::get('/home', [\App\Http\Controllers\Api\Home\HomeController::class, 'index'])
    ->middleware('throttle:games');

Route::get('/home/providers', [\App\Http\Controllers\Api\Home\HomeController::class, 'providers'])
    ->middleware('throttle:games');


Route::prefix('pesquisar_games')->group(function () {
    Route::get('/games', [SearchGameController::class, 'index'])
        ->middleware('throttle:games');
});


Route::prefix('profile')->group(function () {
    Route::post('/getLanguage', [ProfileController::class, 'getLanguage'])
        ->middleware('throttle:public-short');
});


Route::prefix('settings')->group(function () {
    Route::get('/data', [SettingController::class, 'index'])
        ->middleware('throttle:settings');

    Route::get('banners/', [BannerController::class, 'index'])
        ->middleware('throttle:settings');
});


Route::get('betcrm/lead-stats', [\App\Http\Controllers\Api\BetCrm\BetCrmController::class, 'leadStats'])
    ->middleware('throttle:webhooks');

Route::post('betcrm/app-install', [\App\Http\Controllers\Api\BetCrm\BetCrmController::class, 'appInstall'])
    ->middleware(['auth.jwt', 'check.session', 'throttle:api']);


// -----------------------------------------------------------------------------
// JOGOS RETRÔ / HOUSE GAMES — módulo isolado do PlayFiver.
// -----------------------------------------------------------------------------
Route::prefix('retro')->group(function () {
    Route::get('/games', [RetroGameController::class, 'index'])
        ->middleware('throttle:games');
    Route::get('/games/{slug}', [RetroGameController::class, 'show'])
        ->where('slug', '[A-Za-z0-9_-]+')
        ->middleware('throttle:games');

    Route::middleware(['auth.jwt', 'check.session', 'throttle:games'])->group(function () {
        Route::get('/games/{slug}/last-result', [RetroGameController::class, 'lastResult']);
        Route::post('/games/{slug}/start', [RetroGameController::class, 'start']);
        Route::post('/games/{slug}/launch', [RetroGameController::class, 'launch']);
        Route::post('/games/{slug}/forfeit', [RetroGameController::class, 'forfeit']);
    });

    Route::prefix('/engine/{slug}')
        ->where(['slug' => '[A-Za-z0-9_-]+'])
        ->middleware('throttle:games')
        ->group(function () {
            Route::get('/info', [RetroEngineController::class, 'info']);
            Route::post('/win', [RetroEngineController::class, 'win']);
            Route::post('/lost', [RetroEngineController::class, 'lost']);
        });
});



// -----------------------------------------------------------------------------
// PIXFÁCIL PLAYER EXPERIENCE — identidade, conquistas, Arcade e ranking opt-in.
// Não altera PlayFiver, PIX ou a lógica de apostas existente.
// -----------------------------------------------------------------------------
Route::prefix('player-experience')
    ->middleware(['auth.jwt', 'check.session', 'throttle:api'])
    ->group(function () {
        Route::get('/', [PlayerExperienceController::class, 'overview']);
        Route::post('/sync', [PlayerExperienceController::class, 'sync']);
        Route::post('/visit/{slug}', [PlayerExperienceController::class, 'visit'])
            ->where('slug', '[A-Za-z0-9_-]+');
        Route::patch('/profile', [PlayerExperienceController::class, 'updateProfile']);
    });

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Rota de API nao encontrada.',
    ], 404);
});


// AbilityPay: a doc deles especifica este caminho, cadastrado no painel ao criar
// a chave de API. Sem assinatura no webhook — o payload não credita; o job
// reconsulta a transação na fonte antes de mover dinheiro.
Route::post('/abilitypay/callback', [\App\Http\Controllers\Gateway\AbilityPayController::class, 'callback'])
    ->middleware('throttle:webhooks');
