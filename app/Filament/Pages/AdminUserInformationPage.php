<?php



namespace App\Filament\Pages;

use App\Models\AffiliateHistory;
use App\Models\Deposit;
use App\Models\Order;
use App\Models\User;
use App\Models\Withdrawal;
use Filament\Pages\Page;

class AdminUserInformationPage extends Page
{
    protected static string $view = 'filament.pages.admin-user-information-page';

    protected static ?string $title = 'Informações do Usuário';
    protected static ?string $navigationLabel = 'Informações do Usuário';
    protected static ?string $navigationIcon = 'heroicon-o-information-circle';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'usuarios/{record}/informacoes';

    public User $user;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function mount(int | string $record): void
    {
        $this->user = User::query()
            ->with('wallet')
            ->findOrFail($record);
    }

    public function walletData(): array
    {
        $wallet = $this->user->wallet;

        return [
            'balance_withdrawal' => (float) ($wallet?->balance_withdrawal ?? 0),
            'balance' => (float) ($wallet?->balance ?? 0),
            'balance_bonus' => (float) ($wallet?->balance_bonus ?? 0),
            'balance_deposit_rollover' => (float) ($wallet?->balance_deposit_rollover ?? 0),
            'balance_bonus_rollover' => (float) ($wallet?->balance_bonus_rollover ?? 0),
            'refer_rewards' => (float) ($wallet?->refer_rewards ?? 0),
            'total' => (float) ($wallet?->total_balance ?? 0),
        ];
    }

    public function stats(): array
    {
        $userId = $this->user->id;

        $totalDeposits = (float) Deposit::query()
            ->where('user_id', $userId)
            ->where('status', 1)
            ->sum('amount');

        $totalWithdrawals = (float) Withdrawal::query()
            ->where('user_id', $userId)
            ->sum('amount');

        $totalBet = (float) Order::query()
            ->where('user_id', $userId)
            ->where('type', 'bet')
            ->where('refunded', 0)
            ->sum('amount');

        $totalWin = (float) Order::query()
            ->where('user_id', $userId)
            ->where('type', 'win')
            ->where('refunded', 0)
            ->sum('amount');

        $betCount = Order::query()
            ->where('user_id', $userId)
            ->where('type', 'bet')
            ->count();

        $winCount = Order::query()
            ->where('user_id', $userId)
            ->where('type', 'win')
            ->count();

        $affiliate = AffiliateHistory::query()
            ->where('inviter', $userId)
            ->selectRaw("
                coalesce(sum(commission_paid),0) as commission_paid,
                coalesce(sum(case when status = 1 then deposited else 0 end),0) as depositors,
                coalesce(sum(case when status = 1 then deposited_amount else 0 end),0) as generated_volume,
                count(*) as clients
            ")
            ->first();

        $indicatedUserIds = AffiliateHistory::query()
            ->where('inviter', $userId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $indicatedDeposits = $indicatedUserIds->isNotEmpty()
            ? (float) Deposit::query()
                ->whereIn('user_id', $indicatedUserIds)
                ->where('status', 1)
                ->sum('amount')
            : 0.0;

        $indicatedBet = $indicatedUserIds->isNotEmpty()
            ? (float) Order::query()
                ->whereIn('user_id', $indicatedUserIds)
                ->where('type', 'bet')
                ->where('refunded', 0)
                ->sum('amount')
            : 0.0;

        $indicatedWin = $indicatedUserIds->isNotEmpty()
            ? (float) Order::query()
                ->whereIn('user_id', $indicatedUserIds)
                ->where('type', 'win')
                ->where('refunded', 0)
                ->sum('amount')
            : 0.0;

        $wallet = $this->user->wallet;

        return [
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'total_bet' => $totalBet,
            'total_win' => $totalWin,
            'house_result' => $totalBet - $totalWin,
            'bet_count' => $betCount,
            'win_count' => $winCount,
            'affiliate_commission' => (float) ($affiliate->commission_paid ?? 0),
            'affiliate_payable' => (float) ($wallet?->refer_rewards ?? 0),
            'affiliate_depositors' => (int) ($affiliate->depositors ?? 0),
            'affiliate_volume' => (float) ($affiliate->generated_volume ?? 0),
            'affiliate_clients' => (int) ($affiliate->clients ?? 0),
            'affiliate_real_deposits' => $indicatedDeposits,
            'affiliate_indicated_bet' => $indicatedBet,
            'affiliate_indicated_win' => $indicatedWin,
            'affiliate_house_profit' => $indicatedBet - $indicatedWin,
        ];
    }

    public function historyCounts(): array
    {
        return [
            'bets' => Order::query()->where('user_id', $this->user->id)->count(),
            'deposits' => Deposit::query()->where('user_id', $this->user->id)->count(),
            'withdrawals' => Withdrawal::query()->where('user_id', $this->user->id)->count(),
            'indications' => AffiliateHistory::query()->where('inviter', $this->user->id)->count(),
        ];
    }

    public function bets(): array
    {
        return Order::query()
            ->with('gameDetails')
            ->where('user_id', $this->user->id)
            ->latest()
            ->limit(300)
            ->get()
            ->map(fn (Order $order) => [
                'game' => $order->gameDetails?->game_name ?: $order->game,
                'type' => $order->type,
                'wallet' => $order->type_money,
                'amount' => (float) $order->amount,
                'provider' => $order->providers,
                'refunded' => (bool) $order->refunded,
                'round_id' => $order->round_id,
                'created_at' => $order->created_at?->format('d/m/Y H:i'),
            ])
            ->toArray();
    }

    public function deposits(): array
    {
        return Deposit::query()
            ->where('user_id', $this->user->id)
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (Deposit $deposit) => [
                'amount' => (float) $deposit->amount,
                'status' => $deposit->status,
                'type' => $deposit->type,
                'id_transaction' => $deposit->idTransaction ?? $deposit->id_transaction ?? null,
                'created_at' => $deposit->created_at?->format('d/m/Y H:i'),
            ])
            ->toArray();
    }

    public function withdrawals(): array
    {
        return Withdrawal::query()
            ->where('user_id', $this->user->id)
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (Withdrawal $withdrawal) => [
                'amount' => (float) $withdrawal->amount,
                'status' => $withdrawal->status,
                'type' => $withdrawal->type,
                'created_at' => $withdrawal->created_at?->format('d/m/Y H:i'),
            ])
            ->toArray();
    }

    public function indications(): array
    {
        return AffiliateHistory::query()
            ->with('user')
            ->where('inviter', $this->user->id)
            ->latest()
            ->limit(300)
            ->get()
            ->map(fn (AffiliateHistory $history) => [
                'name' => $history->user?->name ?: '-',
                'email' => $history->user?->email ?: '-',
                'commission_type' => $history->commission_type,
                'commission_paid' => (float) $history->commission_paid,
                'status' => $history->status,
                'created_at' => $history->created_at?->format('d/m/Y H:i'),
            ])
            ->toArray();
    }

    public function profileLabel(): string
    {
        if ((bool) ($this->user->is_influencer ?? false)) {
            return 'Influencer';
        }

        $stats = $this->stats();

        if ($stats['total_deposits'] >= 5000 || $stats['total_bet'] >= 15000) {
            return 'VIP';
        }

        if ($stats['total_bet'] >= 5000) {
            return 'Apostador alto';
        }

        if ($stats['total_deposits'] > 0) {
            return 'Depositante';
        }

        if (filled($this->user->inviter_code)) {
            return 'Afiliado';
        }

        if ($this->user->created_at && $this->user->created_at->gte(now()->subDays(7))) {
            return 'Novo';
        }

        return 'Comum';
    }

    public function resultLabel(?string $type): string
    {
        return match ((string) $type) {
            'bet' => 'Perda / aposta',
            'win' => 'Ganho',
            'refund' => 'Estorno',
            default => (string) $type,
        };
    }

    public function walletLabel(?string $wallet): string
    {
        return match ((string) $wallet) {
            'balance' => 'Depósito',
            'balance_bonus' => 'Bônus',
            'balance_withdrawal' => 'Saque',
            'mixed' => 'Mista',
            default => $wallet ?: '-',
        };
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}