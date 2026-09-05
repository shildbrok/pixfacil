<?php



namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateHistory extends Model
{
    use HasFactory;


    protected $table = 'affiliate_histories';
    protected $appends = ['dateHumanReadable', 'createdAt'];


    protected $fillable = [
        'user_id',
        'inviter',
        'commission',
        'commission_type',
        'deposited',
        'deposited_amount',
        'losses',
        'losses_amount',
        'commission_paid',
        'status',
    ];


    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $this->getStatus($value),
        );
    }


    private function getStatus($status)
    {
        switch ((string) $status) {
            case '1':
                return 'pago';
            case '0':
                return 'pendente';
            default:

                return (string) $status;
        }
    }


    public function getCreatedAtAttribute()
    {


        $v = $this->attributes['created_at'] ?? null;
        return $v ? Carbon::parse($v) : null;
    }


    public function getDateHumanReadableAttribute()
    {
        return $this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null;
    }


    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
