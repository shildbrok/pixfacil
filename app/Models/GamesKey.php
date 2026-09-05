<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    protected $hidden = ['updated_at'];
}