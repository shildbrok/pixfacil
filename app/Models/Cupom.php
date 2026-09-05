<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cupom extends Model
{
    use HasFactory;


    protected $table = 'cupons'; 


    protected $fillable = [
        'codigo',
        'valor_bonus',
        'validade',
        'quantidade_uso',
        'usos',
    ];


    public function isValid()
    {
        return $this->validade >= now() && $this->usos < $this->quantidade_uso;
    }
}
