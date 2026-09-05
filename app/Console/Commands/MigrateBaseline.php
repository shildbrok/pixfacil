<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca como JÁ APLICADAS apenas as migrations cujo alvo (tabela) JÁ EXISTE no banco
 * — isto é, o schema baseline que veio do dump SQL. Migrations de tabelas NOVAS são
 * deixadas PENDENTES, para que `php artisan migrate` as execute normalmente.
 *
 * Uso: bancos criados a partir do dump. Idempotente e seguro rodar junto de updates
 * que tragam migrations novas (o baseline nao "engole" as novas).
 *
 *   php artisan migrate:baseline
 *   php artisan migrate            # roda so as migrations de tabelas novas
 */
class MigrateBaseline extends Command
{
    protected $signature = 'migrate:baseline {--fresh-batch : Registra num novo batch em vez do batch 1}';

    protected $description = 'Marca as migrations do schema existente como aplicadas (para bancos vindos do dump SQL).';

    public function handle(): int
    {
        if (! Schema::hasTable('migrations')) {
            $this->warn('Tabela "migrations" ausente — criando via migrate:install...');
            $this->call('migrate:install');
        }

        $files = glob(database_path('migrations/*.php')) ?: [];
        if ($files === []) {
            $this->error('Nenhum arquivo de migration encontrado em database/migrations.');
            return self::FAILURE;
        }

        $existingSet = array_flip(DB::table('migrations')->pluck('migration')->all());
        $batch = $this->option('fresh-batch')
            ? ((int) DB::table('migrations')->max('batch')) + 1
            : 1;

        $marked = 0; $skipped = 0; $pending = 0;

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (isset($existingSet[$name])) {
                $skipped++;
                continue;
            }

            $table = $this->tableFromMigration($file);

            // Marca como aplicada SO se for um schema-migration cuja tabela JA existe.
            // Migrations de tabela NOVA (create) OU de dados/seed (sem Schema::create/table
            // detectavel, ex.: inserts) ficam PENDENTES para o `migrate` executar.
            if ($table === null || ! Schema::hasTable($table)) {
                $pending++;
                continue;
            }

            DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
            $marked++;
        }

        $this->newLine();
        $this->info('Baseline concluido:');
        $this->line("  • Marcadas como aplicadas (ja existiam): <fg=green>{$marked}</>");
        $this->line("  • Ja registradas (ignoradas):            <fg=yellow>{$skipped}</>");
        $this->line("  • Novas, deixadas para o migrate:        <fg=cyan>{$pending}</>");
        $this->newLine();
        if ($pending > 0) {
            $this->line("Rode agora:  <fg=cyan>php artisan migrate</>  (executa as {$pending} migration(s) nova(s)).");
        } else {
            $this->line('Nada pendente. <fg=cyan>php artisan migrate</> daqui pra frente roda so o que for novo.');
        }

        return self::SUCCESS;
    }

    /** Extrai o nome da tabela do primeiro Schema::create()/table() do arquivo. */
    private function tableFromMigration(string $file): ?string
    {
        $content = (string) file_get_contents($file);
        if (preg_match('/Schema::(?:create|table)\(\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            return $m[1];
        }
        return null;
    }
}
