<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promocao extends Model
{
    use HasFactory;


    protected $table = 'promocoes';


    protected $fillable = [
        'imagem',
        'titulo',
        'link',
        'regras_html',
    ];


    public $timestamps = true; 


}
