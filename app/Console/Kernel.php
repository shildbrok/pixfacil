<?php



namespace App\Console;

use App\Jobs\CheckDistributionSystemJob;
use App\Support\OpsHealth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule): void
    {

        $schedule->command('gamesessions:close-inactive')
            ->everyMinute()
            ->withoutOverlapping();

        // Limpeza diária das sessões já encerradas há +7 dias. Sem isto a tabela
        // game_sessions só cresce (o close-inactive marca, mas nunca apaga).
        // 04:00 (America/Sao_Paulo) é horário de baixo movimento.
        $schedule->command('gamesessions:prune --days=7')
            ->dailyAt('04:00')
            ->withoutOverlapping();

        $schedule->command('aggregator:sync-balances')
            ->everyMinute()
            ->withoutOverlapping();


        // Sistema de distribuição roda DENTRO do agendador (sem fila). Assim o
        // CloudPanel precisa de um cron só (schedule:run) — ele não roda worker.
        // O handle() não persiste a troca de modo antes de confirmar o RTP, então
        // uma falha só faz o próximo minuto tentar de novo. O try/catch garante
        // que uma falha aqui nunca derrube as outras tarefas do agendador.
        $schedule->call(function () {
            try {
                (new CheckDistributionSystemJob())->handle();
            } catch (\Throwable $e) {
                Log::warning('[Distribution] falha no ciclo inline: ' . $e->getMessage());
            }
        })
            ->name('distribution-system') // obrigatório ANTES de withoutOverlapping em closure
            ->everyMinute()
            ->withoutOverlapping();


        // Heartbeat do AGENDADOR: roda dentro do schedule:run, então carimbar
        // aqui prova que o cron está disparando. Alimenta a página de status.
        $schedule->call(fn () => OpsHealth::markScheduler())
            ->everyMinute()
            ->name('ops-scheduler-heartbeat');
    }


    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}