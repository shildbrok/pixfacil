<?php



namespace App\Console\Commands;

use App\Models\GameSession;
use Illuminate\Console\Command;

class CloseInactiveGameSessions extends Command
{

    protected $signature = 'gamesessions:close-inactive';


    protected $description = 'Fecha/expira sessões de jogos que estão há mais de 5 minutos sem atualização';


    public function handle(): int
    {
        $cutoff = now()->subMinutes(5);

        $affected = GameSession::where('status', GameSession::STATUS_ACTIVE)
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status'    => GameSession::STATUS_EXPIRED, 
                'closed_at' => now(),
            ]);

        $this->info("Sessões inativas fechadas: {$affected}");

        return Command::SUCCESS;
    }
}
