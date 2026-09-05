<?php



namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Deposit extends Model
{
    use HasFactory;


    protected $table = 'deposits';
    protected $appends = ['dateHumanReadable', 'createdAt'];



    protected $fillable = [
        'payment_id',
        'user_id',
        'amount',
        'type',
        'proof',
        'currency',
        'symbol',
        'status'
    ];


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


    public function users() : BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
