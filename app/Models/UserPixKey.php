<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPixKey extends Model
{
    use HasFactory;

    protected $table = 'user_pix_keys';

    protected $fillable = [
        'user_id',
        'holder_name',
        'holder_cpf',
        'key_type',
        'pix_key',
        'is_default',
    ];

    protected $casts = [
        'user_id'    => 'integer',
        'is_default' => 'boolean',
    ];

    public const TYPE_DOCUMENT = 'document';
    public const TYPE_EMAIL    = 'email';
    public const TYPE_PHONE    = 'phoneNumber';
    public const TYPE_RANDOM   = 'randomKey';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
