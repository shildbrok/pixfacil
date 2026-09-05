<?php



namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateWithdraw extends Model
{
    use HasFactory;


    protected $table = 'affiliate_withdraws';
    protected $appends = ['dateHumanReadable', 'createdAt'];


    protected $fillable = [
        'payment_id',
        'user_id',
        'amount',
        'type',
        'bank_info',
        'type',
        'proof',
        'pix_key',
        'pix_type',
        'currency',
        'symbol',
        'status',
        'cpf',
        'name'
    ];



    public function getCreatedAtAttribute()
    {
        return Carbon::parse($this->attributes['created_at']);
    }


    public function getDateHumanReadableAttribute()
    {
        return Carbon::parse($this->created_at)->diffForHumans();
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
