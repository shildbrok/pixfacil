<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycConfig extends Model
{
    use HasFactory;

    protected $table = 'kyc_configs';

    protected $fillable = [
        'withdrawal_kyc_required',
        'auto_approve_documents',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'withdrawal_kyc_required' => 'boolean',
        'auto_approve_documents' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function current(): self
    {
        $config = static::query()
            ->where('is_active', true)
            ->first();

        if ($config) {
            return $config;
        }

        return static::create([
            'withdrawal_kyc_required' => true,
            'auto_approve_documents' => false,
            'is_active' => true,
            'notes' => 'Configuração criada automaticamente.',
        ]);
    }

    public function isWithdrawalRequired(): bool
    {
        return (bool) $this->withdrawal_kyc_required;
    }

    public function isWithdrawalKycRequired(): bool
    {
        return $this->isWithdrawalRequired();
    }

    public function isAutoApproveDocumentsEnabled(): bool
    {
        return (bool) $this->auto_approve_documents;
    }
}