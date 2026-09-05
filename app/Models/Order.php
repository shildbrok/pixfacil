<?php



namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\Core as Helper;

class Order extends Model
{
    use HasFactory;


    protected $table = 'orders';


    protected $appends = ['dateHumanReadable', 'createdAt', 'type_label', 'provider_label'];


    protected $fillable = [
        'user_id',
        'session_id',
        'transaction_id',
        'game',
        'game_uuid',
        'type',
        'type_money',
        'amount',
        'providers',
        'refunded',
        'round_id',
        'status'
    ];
    

    protected function typeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => Helper::getTypeOrder($this->attributes['type'] ?? null),
        );
    }










    protected function providerLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => Helper::getDistribution()[$this->attributes['providers'] ?? ''] ?? 'Sem Provedor',
        );
    }

    

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function getCreatedAtAttribute()
    {


        $v = $this->attributes['created_at'] ?? null;
        return $v ? Carbon::parse($v) : null;
    }

    public function gameDetails(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game', 'game_id');
    }


    public function getDateHumanReadableAttribute()
    {
        return $this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null;
    }

    
}
