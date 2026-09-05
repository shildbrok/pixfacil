<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class GamesKey extends Model
{
    use HasFactory;

    protected $table = 'games_keys';

    protected $fillable = [
        'playfiver_url',
        'playfiver_secret',
        'playfiver_code',
        'playfiver_token',
    ];

    protected $hidden = ['updated_at', 'playfiver_secret', 'playfiver_code', 'playfiver_token'];

    private function secretAttribute(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }
                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    // Compatibilidade temporária com registros legados em texto puro.
                    return $value;
                }
            },
            set: fn (?string $value) => ($value === null || $value === '') ? null : Crypt::encryptString($value),
        );
    }

    protected function playfiverSecret(): Attribute
    {
        return $this->secretAttribute();
    }

    protected function playfiverCode(): Attribute
    {
        return $this->secretAttribute();
    }

    protected function playfiverToken(): Attribute
    {
        return $this->secretAttribute();
    }
}
