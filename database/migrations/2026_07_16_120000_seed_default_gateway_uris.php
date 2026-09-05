<?php

use App\Services\Gateways\AbilityPay\AbilityPayClient;
use App\Services\Gateways\DigitoPay\DigitoPayClient;
use App\Services\Gateways\ForceOnePay\ForceOnePayClient;
use App\Services\Gateways\Pixup\PixupClient;
use App\Services\Gateways\PodPay\PodPayClient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Preenche a URL de API de cada gateway.
 *
 * Os clients já caem no BASE_URL quando a coluna está vazia, então isto não muda
 * comportamento nenhum — é para o campo aparecer PREENCHIDO no admin. O cliente
 * abre a aba e só precisa colar as credenciais, sem adivinhar a URL.
 *
 * Só escreve onde está NULL/vazio: quem já apontou para homologação ou para um
 * endpoint próprio continua como está.
 */
return new class extends Migration
{
    private const URIS = [
        'digitopay_uri'   => DigitoPayClient::BASE_URL,
        'pixup_uri'       => PixupClient::BASE_URL,
        'podpay_uri'      => PodPayClient::BASE_URL,
        'abilitypay_uri'  => AbilityPayClient::BASE_URL,
        'forceonepay_uri' => ForceOnePayClient::BASE_URL,
    ];

    public function up(): void
    {
        foreach (self::URIS as $coluna => $url) {
            DB::table('gateways')
                ->where(fn ($q) => $q->whereNull($coluna)->orWhere($coluna, ''))
                ->update([$coluna => $url]);
        }
    }

    public function down(): void
    {
        // Volta ao estado anterior apenas onde o valor é exatamente o padrão:
        // uma URL customizada pelo cliente não é nossa para apagar.
        foreach (self::URIS as $coluna => $url) {
            DB::table('gateways')->where($coluna, $url)->update([$coluna => null]);
        }
    }
};
