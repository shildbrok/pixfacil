<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class Gateway extends Model
{
    use HasFactory;

    protected $table = 'gateways';

    protected $fillable = [

        'gerapix_uri',
        'gerapix_secret_token',

        'digitopay_uri',
        'digitopay_client_id',
        'digitopay_secret',

        'pixup_uri',
        'pixup_client_id',
        'pixup_client_secret',
        'pixup_webhook_secret',

        'podpay_uri',
        'podpay_api_key',

        'abilitypay_uri',
        'abilitypay_client_id',
        'abilitypay_client_secret',

        'forceonepay_uri',
        'forceonepay_token',
    ];

    protected $hidden = ['updated_at'];

    private function decryptIfNeeded(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return $value; 
        }
    }


    protected function gerapixSecretToken(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => config('app.demo') ? '*********************' : $this->decryptIfNeeded($value),
            set: fn (?string $value) => empty($value) ? null : Crypt::encryptString($value),
        );
    }

    // DigitoPay: mesmo padrão do GeraPix — criptografado em repouso e mascarado
    // em modo demo. O clientId também é segredo (junto com o secret, ele emite
    // token e move dinheiro), então recebe o mesmo tratamento.

    protected function digitopayClientId(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => config('app.demo') ? '*********************' : $this->decryptIfNeeded($value),
            set: fn (?string $value) => empty($value) ? null : Crypt::encryptString($value),
        );
    }

    protected function digitopaySecret(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => config('app.demo') ? '*********************' : $this->decryptIfNeeded($value),
            set: fn (?string $value) => empty($value) ? null : Crypt::encryptString($value),
        );
    }

    // PIXUP: os quatro segredos, todos criptografados em repouso. O pixup_uri
    // fica de fora de propósito — é só a URL base, não é segredo.

    protected function pixupClientId(): Attribute
    {
        return $this->secretAttribute();
    }

    protected function pixupClientSecret(): Attribute
    {
        return $this->secretAttribute();
    }

    /** Valida o HMAC dos webhooks que chegam. Mesmo valor do dashboard da PIXUP. */
    protected function pixupWebhookSecret(): Attribute
    {
        return $this->secretAttribute();
    }

    /** sk_live_… (produção) ou sk_test_… (sandbox). */
    protected function podpayApiKey(): Attribute
    {
        return $this->secretAttribute();
    }

    protected function abilitypayClientId(): Attribute
    {
        return $this->secretAttribute();
    }

    protected function abilitypayClientSecret(): Attribute
    {
        return $this->secretAttribute();
    }

    /** Token CONTRATADO: emite o Bearer de trabalho. */
    protected function forceonepayToken(): Attribute
    {
        return $this->secretAttribute();
    }

    /** Mesmo tratamento de todo segredo daqui: cripto em repouso, mascarado em demo. */
    private function secretAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => config('app.demo') ? '*********************' : $this->decryptIfNeeded($value),
            set: fn (?string $value) => empty($value) ? null : Crypt::encryptString($value),
        );
    }
}
