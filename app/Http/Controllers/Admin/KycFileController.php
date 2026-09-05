<?php



namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Verificacao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class KycFileController extends Controller
{
    public function show(Verificacao $verificacao, string $type): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('admin')) {
            abort(403);
        }

        $map = [
            'selfie' => 'selfie_path',
            'frente' => 'doc_frente_path',
            'verso' => 'doc_verso_path',
        ];

        if (! isset($map[$type])) {
            abort(404);
        }

        $column = $map[$type];
        $path = (string) ($verificacao->{$column} ?? '');

        $path = str_replace('\\', '/', ltrim($path, '/'));

        if (str_starts_with($path, 'kyc/')) {
            $path = substr($path, 4);
        }

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }

        $disk = Storage::disk('kyc_private');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mime = (string) ($disk->mimeType($path) ?: 'image/jpeg');
        $filename = basename($path);
        $content = $disk->get($path);

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename),
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}