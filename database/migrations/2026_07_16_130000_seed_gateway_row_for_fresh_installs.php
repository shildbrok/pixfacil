<?php

use App\Services\Gateways\AbilityPay\AbilityPayClient;
use App\Services\Gateways\DigitoPay\DigitoPayClient;
use App\Services\Gateways\ForceOnePay\ForceOnePayClient;
use App\Services\Gateways\Pixup\PixupClient;
use App\Services\Gateways\PodPay\PodPayClient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garante a linha de gateways COM as URLs de API preenchidas.
 *
 * A migration anterior (seed_default_gateway_uris) só fazia UPDATE, e numa
 * instalação nova a tabela está vazia: o update não pegava nada e o cliente
 * abria o admin com todas as URLs em branco — exatamente o que preencher as
 * URLs deveria evitar. O admin só cria a linha quando o operador salva, então
 * não havia momento em que os padrões apareceriam sozinhos.
 *
 * Aqui cobrimos os dois casos:
 *   - tabela vazia (cliente novo): INSERT com os padrões;
 *   - linha existente (quem já roda): preenche só o que está em branco.
 * Nenhum dos dois sobrescreve URL que alguém tenha customizado.
 */
return new class extends Migration
{
    private function uris(): array
    {
        return [
            // O GeraPix é o único sem const no código: o fallback dele mora no
            // GeraPixTrait. Repetido aqui para o campo não nascer vazio no admin.
            'gerapix_uri'     => 'https://api.gerapix.digital',
            'digitopay_uri'   => DigitoPayClient::BASE_URL,
            'pixup_uri'       => PixupClient::BASE_URL,
            'podpay_uri'      => PodPayClient::BASE_URL,
            'abilitypay_uri'  => AbilityPayClient::BASE_URL,
            'forceonepay_uri' => ForceOnePayClient::BASE_URL,
        ];
    }

    public function up(): void
    {
        $uris = $this->uris();

        if (DB::table('gateways')->count() === 0) {
            DB::table('gateways')->insert($uris + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        foreach ($uris as $coluna => $url) {
            DB::table('gateways')
                ->where(fn ($q) => $q->whereNull($coluna)->orWhere($coluna, ''))
                ->update([$coluna => $url]);
        }
    }

    public function down(): void
    {
        // Não apaga a linha: ela pode já ter credenciais. Só devolve ao vazio as
        // URLs que ainda estão exatamente no padrão — customizada não é nossa.
        foreach ($this->uris() as $coluna => $url) {
            DB::table('gateways')->where($coluna, $url)->update([$coluna => null]);
        }
    }
};
