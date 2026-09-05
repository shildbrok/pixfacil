<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;


    protected $table = 'transactions';


    protected $fillable = [
        'payment_id',
        // Sem isto, o create() descarta o campo em silêncio e a coluna
        // fica NULL — o webhook do gateway não acha a transação.
        'gateway',
        'user_id',
        'payment_method',
        'price',
        'currency',
        'status',
        'idUnico',
        'gateway_status',
        'pix_copia_e_cola',
        'qr_code_base64',
        'gateway_response',
        'qr_received_at',
        'client_ip',
        'user_agent',
        'fbp',
        'fbc',
        'source_url',
    ];


    protected $casts = [
        'price' => 'float',
        'qr_received_at' => 'datetime',
    ];
}