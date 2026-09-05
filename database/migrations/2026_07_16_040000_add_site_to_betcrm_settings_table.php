<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O `site` identifica a marca no BetCRM e é parte da IDENTIDADE do lead: mudar o
 * formato cria leads duplicados. Ele vinha sendo derivado do host da APP_URL, o
 * que faz uma troca de domínio duplicar a base inteira sem aviso. Passa a poder
 * ser fixado; enquanto ficar nulo, o serviço mantém o valor derivado de antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('betcrm_settings', function (Blueprint $table) {
            $table->string('site', 120)->nullable()->after('base_url');
        });

        // Congela o valor que já vinha sendo enviado, para os leads existentes
        // continuarem casando. Sem isso, fixar o campo depois viraria outra marca.
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $site = $host ? preg_replace('/^www\./', '', $host) : null;

        if ($site) {
            DB::table('betcrm_settings')->whereNull('site')->update(['site' => $site]);
        }
    }

    public function down(): void
    {
        Schema::table('betcrm_settings', function (Blueprint $table) {
            $table->dropColumn('site');
        });
    }
};
