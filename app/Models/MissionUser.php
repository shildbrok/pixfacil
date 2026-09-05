<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionUser extends Model
{
    use HasFactory;


    protected $fillable = [
        'user_id',
        'mission_id',
        'progress_date',
        'current_progress',
        'reward',
        'redeemed',
    ];

    protected $casts = [
        'progress_date'    => 'date',
        'current_progress' => 'float',
        'reward'           => 'float',
        'redeemed'         => 'boolean',
    ];


    public function mission()
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}