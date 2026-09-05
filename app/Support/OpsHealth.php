<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Saúde das tarefas automáticas.
 *
 * Nesta instalação tudo roda por UM cron só: o agendador (schedule:run) executa
 * todas as tarefas na mão, inclusive o sistema de distribuição, e os eventos de
 * Meta/BetCRM saem junto com a requisição web. Não há worker de fila — de
 * propósito, porque o CloudPanel não roda processo permanente.
 *
 * Como o PHP sob o servidor web não enxerga processos do sistema, a checagem de
 * "está rodando?" usa heartbeat: o agendador carimba um horário quando roda, e
 * o status vem da idade desse carimbo.
 */
class OpsHealth
{
    public const SCHEDULER_KEY = 'ops:scheduler_last_run';

    /** Carimbo expira em 1h: some sozinho se o agendador ficar parado de vez. */
    private const HEARTBEAT_TTL = 3600;

    /** Faixas de idade do carimbo, em segundos. */
    private const FRESH = 90;   // rodou no último ciclo → verde
    private const STALE = 300;  // atrasou alguns ciclos → amarelo


    public static function markScheduler(): void
    {
        Cache::put(self::SCHEDULER_KEY, now()->timestamp, self::HEARTBEAT_TTL);
    }


    public static function schedulerStatus(): array
    {
        $ts = Cache::get(self::SCHEDULER_KEY);

        if (! $ts) {
            return [
                'state' => 'down',
                'label' => 'Nunca rodou',
                'color' => 'danger',
                'last'  => null,
                'age'   => null,
            ];
        }

        $age = now()->timestamp - (int) $ts;

        if ($age <= self::FRESH) {
            [$state, $label, $color] = ['ok', 'Rodando', 'success'];
        } elseif ($age <= self::STALE) {
            [$state, $label, $color] = ['late', 'Atrasado', 'warning'];
        } else {
            [$state, $label, $color] = ['down', 'Parado', 'danger'];
        }

        return [
            'state' => $state,
            'label' => $label,
            'color' => $color,
            'last'  => Carbon::createFromTimestamp((int) $ts),
            'age'   => $age,
        ];
    }


    /**
     * Canário da fila. Normalmente ZERO: nada é enfileirado em operação normal.
     * Se crescer, é sinal de que algum código voltou a usar a fila (dispatch)
     * sem haver worker — os jobs ficariam presos aqui. Vale o alerta.
     */
    public static function queueStats(): array
    {
        $pending  = (int) DB::table('jobs')->count();
        $oldestTs = DB::table('jobs')->min('created_at'); // timestamp unix (int)
        $failed   = (int) DB::table('failed_jobs')->count();

        return [
            'pending'    => $pending,
            'oldest_age' => $oldestTs ? now()->timestamp - (int) $oldestTs : null,
            'failed'     => $failed,
        ];
    }


    /** Binário do PHP no formato do CloudPanel (ex.: /usr/bin/php8.2). */
    public static function phpBinary(): string
    {
        return sprintf('/usr/bin/php%d.%d', PHP_MAJOR_VERSION, PHP_MINOR_VERSION);
    }


    /**
     * SÓ o comando (sem o "* * * * *"): no CloudPanel o horário vai nos 5 campos
     * separados e isto vai no campo "Comando". Já com o caminho real e o PHP
     * deste servidor.
     */
    public static function schedulerCommand(): string
    {
        return sprintf(
            '%s %s/artisan schedule:run >> /dev/null 2>&1',
            self::phpBinary(),
            base_path()
        );
    }
}
