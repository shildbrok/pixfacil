<?php



namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasFactory;


    protected static function booted(): void
    {
        static::updated(function (self $withdrawal) {
            if (! $withdrawal->wasChanged('status')) {
                return;
            }

            // 0=Pendente, 1=Aprovado, 2=Cancelado, 9=Em processamento.
            // Antes o 9 era reportado como concluído e o 2 nunca era enviado,
            // então saque em processamento entrava como pago e saque cancelado
            // ficava pago para sempre. A chave é a mesma, então o BetCRM
            // atualiza a transação em vez de duplicar.
            $status = match ((int) $withdrawal->status) {
                9       => \App\Services\BetCrm\BetCrmService::STATUS_PENDING,
                1       => \App\Services\BetCrm\BetCrmService::STATUS_PAID,
                2       => \App\Services\BetCrm\BetCrmService::STATUS_CANCELLED,
                default => null,
            };

            if ($status === null) {
                return;
            }

            $user = \App\Models\User::find($withdrawal->user_id);
            if ($user) {
                \App\Services\BetCrm\BetCrmService::sendWithdrawal(
                    $user,
                    (float) $withdrawal->amount,
                    'wd_' . $withdrawal->id,
                    [
                        'status'   => $status,
                        'currency' => (string) ($withdrawal->currency ?? 'BRL'),
                    ]
                );
            }
        });
    }


    protected $table = 'withdrawals';
    protected $appends = ['dateHumanReadable', 'createdAt'];


    protected $fillable = [
        'payment_id',
        // Sem isto, o create() descarta o campo em silêncio e a coluna
        // fica NULL — o webhook do gateway não acha a transação.
        'gateway',
        'user_id',
        'amount',
        'type',
        'bank_info',
        'type',
        'proof',
        'pix_key',
        'pix_type',
        'currency',
        'symbol',
        'status',
        'cpf',
        'name'
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
}
