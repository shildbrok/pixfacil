<?php



namespace App\Http\Controllers;

use App\Services\CacheNuker;
use Illuminate\Http\Request;

class CacheController extends Controller
{
    public function nuke(Request $request)
    {
        $this->authorize('admin');

        $deep          = filter_var($request->input('deep', true), FILTER_VALIDATE_BOOLEAN);
        $clearSessions = filter_var($request->input('sessions', true), FILTER_VALIDATE_BOOLEAN);
        $clearQueues   = filter_var($request->input('queues', true), FILTER_VALIDATE_BOOLEAN);

        $report = app(CacheNuker::class)->run([
            'deep'     => $deep,
            'sessions' => $clearSessions,
            'queues'   => $clearQueues,
        ]);

        return back()
            ->with('status', 'Cache limpo com sucesso!')
            ->with('cache_report', $report);
    }
}
