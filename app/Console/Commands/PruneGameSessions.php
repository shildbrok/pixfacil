<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use Illuminate\Console\Command;

/**
 * Apaga sessões de jogo já encerradas (expired/closed) mais velhas que N dias.
 *
 * O gamesessions:close-inactive só MARCA sessões como expiradas — nunca apaga.
 * Sem esta limpeza, a tabela game_sessions cresce para sempre. Nada lê o
 * histórico de sessões encerradas (o CRM e o WalletOverview só olham sessões
 * ativas dos últimos 5 min), então apagar as antigas é seguro.
 *
 * Só toca em expired/closed: uma sessão 'active' nunca é apagada aqui.
 */
class PruneGameSessions extends Command
{
    protected $signature = 'gamesessions:prune {--days=7 : Idade mínima, em dias, para apagar}';

    protected $description = 'Apaga sessões de jogo encerradas (expired/closed) mais velhas que N dias';

    public function handle(): int
    {
        $days   = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $total = 0;

        // Deleta em lotes: um DELETE único numa tabela grande travaria a tabela
        // e brigaria com os inserts de sessão que acontecem o tempo todo.
        do {
            $deleted = GameSession::query()
                ->whereIn('status', [GameSession::STATUS_EXPIRED, GameSession::STATUS_CLOSED])
                ->where('updated_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        $this->info("Sessões encerradas apagadas (>{$days}d): {$total}");

        return Command::SUCCESS;
    }
}
