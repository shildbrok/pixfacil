<?php



namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;


class BetcrmSetting extends Model
{
    use HasFactory;

    protected $table = 'betcrm_settings';

    protected $fillable = [
        'enabled',
        'base_url',
        'site',
        'client_id',
        'client_secret',
        'send_registration',
        'send_login',
        'send_app_install',
        'send_deposit',
        'send_withdrawal',
    ];

    protected $casts = [
        'enabled'           => 'boolean',
        'send_registration' => 'boolean',
        'send_login'        => 'boolean',
        'send_app_install'  => 'boolean',
        'send_deposit'      => 'boolean',
        'send_withdrawal'   => 'boolean',
    ];


    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'enabled'  => false,
            'base_url' => 'https://betcrm.io/api/v1',
        ]);
    }

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


    protected function clientSecret(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => config('app.demo') ? '*********************' : $this->decryptIfNeeded($value),
            set: fn (?string $value) => empty($value) ? null : Crypt::encryptString($value),
        );
    }
}