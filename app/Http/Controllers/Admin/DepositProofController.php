<?php



namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepositProofController extends Controller
{
    public function show(Deposit $deposit): StreamedResponse
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('admin')) {
            abort(403);
        }

        $path = (string) ($deposit->proof ?? '');
        $path = str_replace('\\', '/', ltrim($path, '/'));

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }


        $private = Storage::disk('private');
        if ($private->exists($path)) {
            $mime = (string) ($private->mimeType($path) ?: 'application/octet-stream');
            $filename = basename($path);

            return response()->streamDownload(function () use ($private, $path) {
                echo $private->get($path);
            }, $filename, [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename),
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        }


        $public = Storage::disk('public');
        if ($public->exists($path)) {
            $mime = (string) ($public->mimeType($path) ?: 'application/octet-stream');
            $filename = basename($path);

            return response()->streamDownload(function () use ($public, $path) {
                echo $public->get($path);
            }, $filename, [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename),
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        }

        abort(404);
    }
}
