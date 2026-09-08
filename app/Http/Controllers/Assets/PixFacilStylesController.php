<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class PixFacilStylesController extends Controller
{
    public function __invoke(): Response
    {
        $path = public_path('pixfacil-v15/pixfacil-v15.css');

        if (! is_file($path)) {
            abort(404);
        }

        $source = file_get_contents($path);

        if ($source === false) {
            abort(500, 'Não foi possível carregar os estilos do tema.');
        }

        // O V15 foi criado mobile-first e concentra a base visual completa em
        // media queries <= 767px. Para o desktop reutilizamos exatamente essa
        // base visual, ativando-a também >= 768px. O pixfacil-desktop.css,
        // carregado depois, fica responsável somente por layout/adaptações.
        $source = str_replace(
            ['@media(max-width:767px){', '@media (max-width:767px){', '@media (max-width: 767px) {'],
            ['@media(min-width:768px){', '@media (min-width:768px){', '@media (min-width: 768px) {'],
            $source
        );

        return response($source, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
