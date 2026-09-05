<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    use HasFactory;

    protected $table = 'game_sessions';


    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int'; 


    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'game_id',
        'provider',
        'status',
        'started_at',
        'last_ping_at',
        'closed_at',
        'device',
        'ip',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'game_id'      => 'integer',
        'started_at'   => 'datetime',
        'last_ping_at' => 'datetime',
        'closed_at'    => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_CLOSED  = 'closed';
    public const STATUS_EXPIRED = 'expired';


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
