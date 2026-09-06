<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class PixFacilRuntimeController extends Controller
{
    public function __invoke(): Response
    {
        $path = public_path('pixfacil-v15/pixfacil-v15.js');

        if (! is_file($path)) {
            abort(404);
        }

        $source = file_get_contents($path);

        if ($source === false) {
            abort(500, 'Não foi possível carregar o runtime do tema.');
        }

        // O renderer V15 continua sendo a base das páginas internas em desktop.
        // A Home desktop possui renderer próprio para reproduzir a experiência
        // aprovada sem manter duas interfaces concorrendo na mesma tela.
        $source = str_replace(
            "if(!MOBILE()||/^\\/admin(?:\\/|$)/i.test(p)||gamePath(p))return false;",
            "if(/^\\/admin(?:\\/|$)/i.test(p)||gamePath(p)||(window.innerWidth>=768&&p==='/'))return false;",
            $source
        );

        // Compatibilidade com rotas legadas do tema original.
        $source = str_replace(
            "^\\/(?:casino|pesquisar|search)",
            "^\\/(?:casino|cassino|games|jogos|slots|live-casino|pesquisar|search)",
            $source
        );

        $source = str_replace(
            "else if(/^\\/casino/i.test(p)||/^\\/(?:pesquisar|search)/i.test(p))await renderCasino(seq);",
            "else if(/^\\/(?:casino|cassino|games|jogos|slots|live-casino)/i.test(p)||/^\\/(?:pesquisar|search)/i.test(p))await renderCasino(seq);",
            $source
        );

        return response($source, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
