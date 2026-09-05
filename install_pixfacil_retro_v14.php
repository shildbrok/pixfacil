<?php
/**
 * PixFácil V14 — instalador seguro do módulo Jogos Retrô.
 * Faz apenas inserções pontuais nos 3 arquivos existentes e cria backups.
 * Pode ser executado mais de uma vez.
 */

declare(strict_types=1);

$root = __DIR__;

function failInstall(string $message): never
{
    fwrite(STDERR, "ERRO: {$message}\n");
    exit(1);
}

function readRequired(string $path): string
{
    if (!is_file($path)) {
        failInstall("arquivo não encontrado: {$path}");
    }
    $data = file_get_contents($path);
    if ($data === false) {
        failInstall("não foi possível ler: {$path}");
    }
    return $data;
}

function backupOnce(string $path): void
{
    $backup = $path . '.before-retro-v14.bak';
    if (!is_file($backup) && !copy($path, $backup)) {
        failInstall("não foi possível criar backup: {$backup}");
    }
}

function atomicWrite(string $path, string $contents): void
{
    $tmp = $path . '.retro-v14.tmp';
    if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
        failInstall("não foi possível gravar temporário: {$tmp}");
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        failInstall("não foi possível substituir: {$path}");
    }
}

// -------------------------------------------------------------------------
// 1) routes/api.php — preserva todas as rotas atuais e acrescenta as retrô.
// -------------------------------------------------------------------------
$routePath = $root . '/routes/api.php';
$routes = readRequired($routePath);
$newRoutes = $routes;

$imports = "use App\\Http\\Controllers\\Api\\Retro\\RetroGameController;\n"
         . "use App\\Http\\Controllers\\Api\\Retro\\RetroEngineController;\n";

if (!str_contains($newRoutes, 'Api\\Retro\\RetroGameController')) {
    $anchor = 'use App\\Http\\Controllers\\Api\\RoundsFreeConfigController;';
    if (str_contains($newRoutes, $anchor)) {
        $newRoutes = str_replace($anchor, $anchor . "\n" . $imports, $newRoutes);
    } else {
        $pos = strpos($newRoutes, 'Route::');
        if ($pos === false) {
            failInstall('não encontrei um ponto seguro para inserir imports em routes/api.php');
        }
        $newRoutes = substr($newRoutes, 0, $pos) . $imports . "\n" . substr($newRoutes, $pos);
    }
}

$routeBlock = <<<'PHP'

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
            Route::match(['GET', 'POST'], '/win', [RetroEngineController::class, 'win']);
            Route::match(['GET', 'POST'], '/lost', [RetroEngineController::class, 'lost']);
        });
});
PHP;

if (!str_contains($newRoutes, 'JOGOS RETRÔ / HOUSE GAMES')) {
    $fallback = 'Route::fallback(function () {';
    if (str_contains($newRoutes, $fallback)) {
        $newRoutes = str_replace($fallback, $routeBlock . "\n\n" . $fallback, $newRoutes);
    } else {
        $newRoutes = rtrim($newRoutes) . "\n" . $routeBlock . "\n";
    }
}

// -------------------------------------------------------------------------
// 2) AdminPanelProvider — só acrescenta os dois itens ao grupo de jogos.
// -------------------------------------------------------------------------
$adminPath = $root . '/app/Providers/Filament/AdminPanelProvider.php';
$admin = readRequired($adminPath);
$newAdmin = $admin;

if (!str_contains($newAdmin, 'AdminRetroGamesPage')) {
    $anchor = 'use App\\Filament\\Pages\\AdminHomeSectionsPage;';
    if (!str_contains($newAdmin, $anchor)) {
        failInstall('âncora AdminHomeSectionsPage não encontrada no AdminPanelProvider');
    }
    $newAdmin = str_replace(
        $anchor,
        $anchor . "\nuse App\\Filament\\Pages\\AdminRetroGamesPage;\nuse App\\Filament\\Pages\\AdminRetroRoundsPage;",
        $newAdmin
    );
}

if (!str_contains($newAdmin, "'retro-games'")) {
    $anchor = "                \$this->pageItem('home-sections', 'Secoes da Home', 'heroicon-o-rectangle-stack', url(config('app.filament_base_url', 'admin').'/secoes-da-home'), ['filament.admin.pages.secoes-da-home']),";
    if (!str_contains($newAdmin, $anchor)) {
        failInstall('âncora Secoes da Home não encontrada no grupo GESTAO DE JOGOS');
    }
    $items = "\n"
        . "                \$this->pageItem('retro-games', 'Jogos Retrô', 'heroicon-o-command-line', AdminRetroGamesPage::getUrl(), ['filament.admin.pages.jogos-retro']),\n"
        . "                \$this->pageItem('retro-rounds', 'Rodadas Retrô', 'heroicon-o-clock', AdminRetroRoundsPage::getUrl(), ['filament.admin.pages.rodadas-retro']),";
    $newAdmin = str_replace($anchor, $anchor . $items, $newAdmin);
}

// -------------------------------------------------------------------------
// 3) app.blade.php — aponta o frontend mobile atual para V14 e inclui /retro.
// -------------------------------------------------------------------------
$bladePath = $root . '/resources/views/layouts/app.blade.php';
$blade = readRequired($bladePath);
$newBlade = $blade;

if (!str_contains($newBlade, "preg_match('#^/retro(?:/|$)#i'")) {
    $supportPattern = "            || preg_match('#^/support-center(?:/|$)#i', \$pf9RequestPath)";
    if (!str_contains($newBlade, $supportPattern)) {
        failInstall('âncora support-center não encontrada no app.blade.php');
    }
    $newBlade = str_replace(
        $supportPattern,
        $supportPattern . "\n            || preg_match('#^/retro(?:/|$)#i', \$pf9RequestPath)",
        $newBlade
    );
}

// Fallbacks de logo/banner do tema.
$newBlade = preg_replace("#pixfacil-v[0-9]+/logo\\.svg#", 'pixfacil-v14/logo.svg', $newBlade) ?? $newBlade;
$newBlade = preg_replace("#pixfacil-v[0-9]+/hero\\.webp#", 'pixfacil-v14/hero.webp', $newBlade) ?? $newBlade;

// Variável server-owned usada pelo JavaScript do tema.
$newBlade = preg_replace(
    '/window\\.PIXFACIL_V[0-9]+_SERVER_OWNED\\s*=/',
    'window.PIXFACIL_V14_SERVER_OWNED =',
    $newBlade,
    1
) ?? $newBlade;

// CSS/JS únicos para evitar cache de versões anteriores.
$newBlade = preg_replace(
    "#\\{\\{ asset\\('pixfacil-v[0-9]+/pixfacil-v[0-9]+\\.css'\\) \\}\\}\\?v=[0-9-]+#",
    "{{ asset('pixfacil-v14/pixfacil-v14.css') }}?v=20260904-140",
    $newBlade,
    1
) ?? $newBlade;
$newBlade = preg_replace(
    "#\\{\\{ asset\\('pixfacil-v[0-9]+/pixfacil-v[0-9]+\\.js'\\) \\}\\}\\?v=[0-9-]+#",
    "{{ asset('pixfacil-v14/pixfacil-v14.js') }}?v=20260904-140",
    $newBlade,
    1
) ?? $newBlade;

$newBlade = preg_replace('/\\bpf[0-9]+-owned-server\\b/', 'pf14-owned-server', $newBlade) ?? $newBlade;
$newBlade = preg_replace('/id="pixfacil-v[0-9]+-app"/', 'id="pixfacil-v14-app"', $newBlade, 1) ?? $newBlade;

if (!str_contains($newBlade, "pixfacil-v14/pixfacil-v14.js")) {
    failInstall('não consegui atualizar o JavaScript do tema para V14');
}
if (!str_contains($newBlade, 'id="pixfacil-v14-app"')) {
    failInstall('não consegui atualizar o root mobile para V14');
}

// Só escreve depois de todas as três transformações terem sido validadas.
$changes = [
    [$routePath, $routes, $newRoutes],
    [$adminPath, $admin, $newAdmin],
    [$bladePath, $blade, $newBlade],
];

$changed = 0;
foreach ($changes as [$path, $old, $new]) {
    if ($old === $new) {
        echo "OK sem alteração: {$path}\n";
        continue;
    }
    backupOnce($path);
    atomicWrite($path, $new);
    $changed++;
    echo "ATUALIZADO: {$path}\n";
}

echo "\nPixFácil V14 instalado no código. Arquivos alterados: {$changed}.\n";
echo "Agora execute:\n";
echo "  php artisan migrate --force\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan view:clear\n";
echo "\nVersão: /pixfacil-v14/VERSION.txt\n";
