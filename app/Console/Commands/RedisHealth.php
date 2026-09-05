<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Diagnóstico do Redis. O cliente EXPERIENTE roda isto depois de ligar
 * REDIS_ENABLED=true, para confirmar que o Redis está no ar e configurado.
 *
 *   php artisan redis:health
 */
class RedisHealth extends Command
{
    protected $signature = 'redis:health';

    protected $description = 'Verifica se o Redis está acessível quando REDIS_ENABLED=true.';

    public function handle(): int
    {
        $enabled = filter_var(config('app.redis_enabled', false), FILTER_VALIDATE_BOOLEAN);

        $this->newLine();
        $this->line('  REDIS_ENABLED = '.($enabled ? '<fg=green>true</>' : '<fg=yellow>false</>'));

        if (! $enabled) {
            $this->line('  Redis está <fg=yellow>desativado</> — o sistema usa file/database (padrão).');
            $this->line('  Nada a verificar. Para ativar, defina REDIS_ENABLED=true no .env.');
            $this->newLine();
            return self::SUCCESS;
        }

        $client = (string) config('database.redis.client', 'phpredis');
        $host   = (string) config('database.redis.default.host', '127.0.0.1');
        $port   = (string) config('database.redis.default.port', '6379');
        $this->line("  Cliente ....... {$client}");
        $this->line("  Host:Porta .... {$host}:{$port}");

        // Aviso se escolheu phpredis mas a extensão não está instalada
        if ($client === 'phpredis' && ! extension_loaded('redis')) {
            $this->newLine();
            $this->warn('  A extensão PHP "redis" (phpredis) NÃO está instalada.');
            $this->line('  Opção mais simples: use o predis (já incluído). Defina no .env:');
            $this->line('      REDIS_CLIENT=predis');
            $this->newLine();
            return self::FAILURE;
        }

        try {
            $pong = Redis::connection()->ping();
            $ok = $pong === true || strtoupper((string) $pong) === 'PONG' || $pong === '+PONG';

            if ($ok) {
                $this->newLine();
                $this->info('  ✅ Redis respondeu (PONG). Cache, sessão e fila estão usando Redis.');
                $this->line('  Lembre: com Redis a fila exige um worker — rode via Supervisor:');
                $this->line('      php artisan queue:work');
                $this->newLine();
                return self::SUCCESS;
            }

            $this->newLine();
            $this->error('  Redis respondeu, mas de forma inesperada: '.var_export($pong, true));
            $this->newLine();
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('  ❌ Não foi possível conectar ao Redis.');
            $this->line('  Erro: '.$e->getMessage());
            $this->newLine();
            $this->line('  Verifique se o servidor Redis está rodando e se REDIS_HOST/PORT/PASSWORD');
            $this->line('  estão corretos no .env. Enquanto não resolver, volte para REDIS_ENABLED=false.');
            $this->newLine();
            return self::FAILURE;
        }
    }
}
