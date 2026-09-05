<?php



namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateLogs extends Model
{
    use HasFactory;


    protected $table = 'affiliate_logs';
    protected $appends = ['dateHumanReadable', 'createdAt'];


    protected $fillable = [
        'user_id',
        'commission',
        'commission_type',
        'type'
    ];

    

    public function getCreatedAtAttribute()
    {
        return Carbon::parse($this->attributes['created_at']);
    }


    public function getDateHumanReadableAttribute()
    {
        return Carbon::parse($this->created_at)->diffForHumans();
    }


    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
