<?php



namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmExportsPage extends Page
{
    protected static string $view = 'filament.pages.crm-exports-page';

    protected static ?string $title = 'Exportações CRM';
    protected static ?string $navigationLabel = 'Exportações';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'crm-exportacoes';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function exportAllClients(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id',
                'nome',
                'email',
                'telefone',
                'cpf',
                'total_apostado',
                'total_depositado',
                'total_ganho',
                'total_perda',
                'resultado_casa',
                'total_sacado',
                'saldo_atual',
                'perfil',
                'afiliado',
                'influenciador',
                'ultima_atividade',
                'cadastro',
            ], ';');

            $this->crmQuery()
                ->orderBy('users.id')
                ->chunk(500, function ($records) use ($handle): void {
                    foreach ($records as $record) {
                        fputcsv($handle, [
                            $record->id,
                            $record->name,
                            $record->email,
                            $record->phone,
                            $record->cpf,
                            $this->fmt($record->bet_volume),
                            $this->fmt($record->total_deposited),
                            $this->fmt($record->win_volume),
                            $this->fmt($record->loss_volume),
                            $this->fmt($record->house_result),
                            $this->fmt($record->total_withdrawn),
                            $this->fmt($record->wallet_total),
                            $this->classifyClient($record),
                            $record->inviter ? (User::find($record->inviter)?->email ?: $record->inviter) : '',
                            (int) ($record->is_influencer ?? 0) === 1 ? 'sim' : 'nao',
                            $record->last_activity_at,
                            $record->created_at,
                        ], ';');
                    }
                });

            fclose($handle);
        }, 'crm_clientes_completo_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function crmQuery()
    {
        return User::query()
            ->with('wallet')
            ->select('users.*')
            ->selectSub("select coalesce(sum(amount),0) from deposits where deposits.user_id = users.id and deposits.status = 1", 'total_deposited')
            ->selectSub("select coalesce(sum(amount),0) from withdrawals where withdrawals.user_id = users.id and withdrawals.status in (1,'paid','approved')", 'total_withdrawn')
            ->selectSub("select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'bet'", 'bet_volume')
            ->selectSub("select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'win'", 'win_volume')
            ->selectSub("greatest((select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'bet') - (select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'win'),0)", 'loss_volume')
            ->selectSub("(select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'bet') - (select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'win')", 'house_result')
            ->selectSub("select coalesce(balance,0)+coalesce(balance_bonus,0)+coalesce(balance_withdrawal,0) from wallets where wallets.user_id = users.id and wallets.active = 1 limit 1", 'wallet_total')
            ->selectSub("select count(distinct date(activity_date)) from (select orders.user_id, orders.created_at as activity_date from orders union all select transactions.user_id, transactions.created_at as activity_date from transactions union all select daily_bonus_claims.user_id, daily_bonus_claims.claimed_at as activity_date from daily_bonus_claims) activity where activity.user_id = users.id and activity.activity_date >= date_sub(now(), interval 30 day)", 'activity_days')
            ->selectSub("select max(activity_date) from (select orders.user_id, orders.created_at as activity_date from orders union all select transactions.user_id, transactions.created_at as activity_date from transactions union all select daily_bonus_claims.user_id, daily_bonus_claims.claimed_at as activity_date from daily_bonus_claims) activity where activity.user_id = users.id", 'last_activity_at');
    }

    private function classifyClient(User $record): string
    {
        if ((bool) ($record->is_influencer ?? false)) {
            return 'Influenciador';
        }

        $days = (int) ($record->activity_days ?? 0);
        $deposited = (float) ($record->total_deposited ?? 0);
        $bet = (float) ($record->bet_volume ?? 0);
        $wallet = (float) ($record->wallet_total ?? 0);
        $last = $record->last_activity_at ? \Carbon\Carbon::parse($record->last_activity_at) : null;

        if ($deposited >= 5000 || $bet >= 15000) {
            return 'VIP';
        }

        if ($bet >= 5000) {
            return 'Apostador alto';
        }

        if ($deposited > 0 && $last && $last->lt(now()->subDays(15))) {
            return 'Risco de abandono';
        }

        if ($days >= 10 || $deposited >= 500) {
            return 'Recorrente';
        }

        if ($deposited > 0) {
            return 'Depositante';
        }

        if ($record->created_at && $record->created_at->gte(now()->subDays(7))) {
            return 'Novo';
        }

        if ($wallet > 0) {
            return 'Saldo parado';
        }

        return 'Inativo';
    }

    private function fmt($value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
