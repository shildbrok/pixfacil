<?php



namespace App\Jobs;

use App\Models\DistributionSystem;
use App\Models\Order;
use App\Models\GamesKey;
use App\Models\GameSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckDistributionSystemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public int $timeout = 30;


    public int $tries = 3;


    public function __construct()
    {

        $this->onQueue('system');
    }

    /**
     * Impede execução concorrente: com o loop de 5s + o scheduler de 1min
     * despachando este job, dois workers poderiam alternar o modo/gravar RTP
     * ao mesmo tempo. O lock serializa a execução; duplicatas são descartadas.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('distribution-system'))
                ->dontRelease()
                ->expireAfter(60),
        ];
    }

    public function handle(): void
    {

        $distribution = DistributionSystem::first();

        if (!$distribution) {
            Log::warning('DistributionSystem: nenhum registro encontrado.');
            return;
        }


        if (!$distribution->ativo) {
            Log::info('DistributionSystem: sistema inativo, nada a fazer.');
            return;
        }


        if (!$distribution->start_cycle_at) {
            $distribution->update(['start_cycle_at' => now()]);
        }


        if ($distribution->modo === 'arrecadacao') {
            $this->processArrecadacao($distribution);
        }

        elseif ($distribution->modo === 'distribuicao') {
            $this->processDistribuicao($distribution);
        }
    }

    private function processArrecadacao(DistributionSystem $distribution): void
    {

        $totalBets = Order::where('type', 'bet')
            ->where('created_at', '>=', $distribution->start_cycle_at)
            ->sum('amount');

        $distribution->total_arrecadado = $totalBets;
        $distribution->save();


        if ($distribution->total_arrecadado >= $distribution->meta_arrecadacao) {

            // Atualiza o RTP externo ANTES de persistir a troca de modo; se falhar,
            // lança e o job re-tenta sem deixar DB e provedor dessincronizados.
            $this->updateRTP($distribution->rtp_distribuicao);

            $distribution->total_arrecadado = 0;
            $distribution->modo = 'distribuicao';
            $distribution->start_cycle_at = now();
            $distribution->save();


            $this->closeActiveSessions();

            Log::info('DistributionSystem: meta de arrecadação alcançada, alternando para DISTRIBUICAO.');
        }
    }

    private function processDistribuicao(DistributionSystem $distribution): void
    {

        $totalWins = Order::where('type', 'win')
            ->where('created_at', '>=', $distribution->start_cycle_at)
            ->sum('amount');

        $distribution->total_distribuido = $totalWins;
        $distribution->save();


        $valorDistribuir = $distribution->meta_arrecadacao * ($distribution->percentual_distribuicao / 100);

        if ($distribution->total_distribuido >= $valorDistribuir) {

            // Atualiza o RTP externo ANTES de persistir a troca de modo; se falhar,
            // lança e o job re-tenta sem deixar DB e provedor dessincronizados.
            $this->updateRTP($distribution->rtp_arrecadacao);

            $distribution->total_distribuido = 0;
            $distribution->modo = 'arrecadacao';
            $distribution->start_cycle_at = now();
            $distribution->save();


            $this->closeActiveSessions();

            Log::info('DistributionSystem: meta de distribuição alcançada, alternando para ARRECADACAO.');
        }
    }


    private function closeActiveSessions(): void
    {
        $afetadas = GameSession::where('status', 'active')
            ->update(['status' => 'closed']);

        Log::info("GameSession: {$afetadas} sessões ativas foram fechadas devido à troca de modo.");
    }


    private function updateRTP($rtp): void
    {
        $setting = GamesKey::first();
        if (!$setting) {
            Log::error('GamesKey não encontrado. Não foi possível atualizar RTP.');
            throw new \RuntimeException('GamesKey não encontrado ao atualizar RTP.');
        }

        // Deve lançar em falha: o modo só é persistido DEPOIS de confirmar o RTP,
        // então uma falha aqui faz o job re-tentar sem dessincronizar DB x provedor.
        $response = Http::withOptions(['force_ip_resolve' => 'v4'])
            ->timeout(15)
            ->put('https://api.playfivers.com/api/v2/agent', [
                'agentToken'   => $setting->playfiver_token,
                'secretKey'    => $setting->playfiver_secret,
                'rtp'          => $rtp,
                'bonus_enable' => true,
            ]);

        if (!$response->successful()) {
            Log::error("Falha ao atualizar RTP (HTTP {$response->status()}) para valor: {$rtp}", [
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Falha ao atualizar RTP: HTTP {$response->status()}");
        }

        Log::info("RTP atualizado com sucesso para valor: {$rtp}");
    }
}
