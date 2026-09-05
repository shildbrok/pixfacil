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

        // O V15 foi originalmente escrito como renderer mobile-only. No desktop
        // queremos reutilizar exatamente o mesmo renderer/rotas/dados, mudando
        // apenas o layout via CSS. Mantemos Admin e players fora do ownership.
        $source = str_replace(
            "if(!MOBILE()||/^\\/admin(?:\\/|$)/i.test(p)||gamePath(p))return false;",
            "if(/^\\/admin(?:\\/|$)/i.test(p)||gamePath(p))return false;",
            $source
        );

        return response($source, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
