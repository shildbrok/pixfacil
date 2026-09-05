<?php



namespace App\Models;

use Carbon\Carbon;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable implements FilamentUser, JWTSubject
{
    // Sem HasApiTokens: a autenticação da API é JWT (guard 'api', tymon/jwt-auth).
    // O Sanctum nunca foi usado aqui — ninguém emitia token e a tabela
    // personal_access_tokens jamais existiu neste banco.
    use HasFactory, Notifiable, HasRoles;


    protected $fillable = [
        'avatar',
        'name',
        'cpf',
        'phone',
        'email',
        'password',
        'admin_action_pin',

        'banned',
        'status',
        'is_influencer',
        'inviter',
        'inviter_code',
        'betcrm_ref',
        'affiliate_cpa',
        'affiliate_baseline',
        'language',

    ];


    protected $casts = [
        'is_influencer' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'admin_action_pin',
        'remember_token',
        'session_token', 
    ];


    protected $appends = ['dateHumanReadable', 'created_at_formatted'];


    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Hash::make($value),
        );
    }


    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter', 'id');
    }


    public function wallet(): HasOne
    {



        return $this->hasOne(Wallet::class);
    }


    public function affiliateHistory(): HasOne
    {
        return $this->hasOne(AffiliateHistory::class);
    }


    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole(['admin']);
    }


    public function getDateHumanReadableAttribute(): string
    {
        return optional($this->created_at)->diffForHumans() ?? '';
    }


    public function getCreatedAtFormattedAttribute(): string
    {
        return optional($this->created_at)->format('Y-m-d') ?? '';
    }


    public function toArray(): array
    {
        $array = parent::toArray();
        $array['createdAt'] = $this->getCreatedAtFormattedAttribute();
        return $array;
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }


    public function getJWTCustomClaims(): array
    {
        return [];
    }


    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class, 'user_id');
    }


    public function activeGameSession(): HasOne
    {
        return $this->hasOne(GameSession::class, 'user_id')
            ->where('status', GameSession::STATUS_ACTIVE)
            ->latest('started_at');
    }

    public function pixKeys(): HasMany
    {
        return $this->hasMany(UserPixKey::class);
    }

    public function isInfluencerMode(): bool
    {
        return (bool) $this->is_influencer;
    }
}