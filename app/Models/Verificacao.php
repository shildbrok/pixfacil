<?php



namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Verificacao extends Model
{
    use HasFactory;


    protected $table = 'verificacoes';


    protected $fillable = [
        'user_id',
        'nome_completo',
        'cpf',
        'selfie_path',
        'doc_frente_path',
        'doc_verso_path',
        'status',
        'observacao_rejeicao',
        'aprovado_por',
        'aprovado_em',
    ];


    protected $casts = [
        'user_id'        => 'integer',
        'aprovado_por'   => 'integer',
        'aprovado_em'    => 'datetime',
    ];


    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }


    public function aprovar(int $adminId): void
    {
        $this->status               = self::STATUS_APPROVED;
        $this->aprovado_por         = $adminId;
        $this->aprovado_em          = Carbon::now();
        $this->observacao_rejeicao  = null;
        $this->save();
    }


    public function rejeitar(int $adminId, string $motivo): void
    {
        $this->status               = self::STATUS_REJECTED;
        $this->aprovado_por         = $adminId;
        $this->aprovado_em          = Carbon::now();
        $this->observacao_rejeicao  = $motivo;
        $this->save();
    }
}
