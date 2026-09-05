<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semeia a ÚNICA linha de configuração global (settings id=1).
 *
 * Sem esta linha várias telas quebram (o app espera sempre 1 registro de settings).
 * Os valores são defaults white-label neutros — o cliente ajusta tudo pelo painel
 * (Configurações da Plataforma). Logos/favicon ficam nulos até o cliente subir os dele.
 *
 * Idempotente: updateOrInsert por id=1.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('settings')->updateOrInsert(
            ['id' => 1],
            [
                // SEO — placeholders neutros
                'meta_description'    => 'Cassino online com jogos ao vivo, slots e apostas. Depósitos e saques rápidos via Pix.',
                'meta_keywords'       => 'cassino online, slots, cassino ao vivo, apostas, pix',
                'og_title'            => 'Cassino Online',
                'og_description'      => 'Jogue slots, cassino ao vivo e mais. Depósitos e saques via Pix.',
                'twitter_title'       => 'Cassino Online',
                'twitter_description' => 'Jogue slots, cassino ao vivo e mais. Depósitos e saques via Pix.',
                'allow_indexing'      => 1,

                // Identidade — cliente configura no painel
                'site_url'            => config('app.url', 'https://seudominio.com'),
                'software_name'       => config('app.name', 'Cassino'),
                'software_favicon'    => null,
                'software_logo_white' => null,
                'software_logo_black' => null,

                // Regras financeiras — defaults sensatos (ajustáveis no painel)
                'initial_bonus'             => 0,
                'min_deposit'               => 10.00,
                'max_deposit'               => 2000.00,
                'min_withdrawal'            => 10.00,
                'max_withdrawal'            => 10000.00,
                'rollover'                  => 5,
                'rollover_deposit'          => 0,
                'revshare_reverse'          => 0,
                'withdrawal_limit'          => 10000,
                'withdrawal_period'         => 'daily',
                'withdrawal_auto_approve'   => 0,
                'withdrawal_auto_approve_max' => 40.00,
                'saque'                     => 'gerapix',
                'cpa_baseline'              => 5.00,
                'cpa_value'                 => 10.00,
                'disable_rollover'          => 0,
                'gerapix_is_enable'         => 1,
                'deposit_gateway'           => 'gerapix',

                'updated_at' => $now,
            ]
        );

        $this->command?->info('Settings: linha padrão (id=1) garantida.');
    }
}
