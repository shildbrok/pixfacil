<?php

namespace App\Filament\Pages;

use App\Models\AdminAuditLog;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * C-02 (viewer): consulta somente-leitura da trilha de auditoria de admin.
 * Registros sao imutaveis — esta pagina nao edita nem apaga.
 */
class AdminAuditLogsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-audit-logs-page';

    protected static ?string $title = 'Auditoria de Ações';
    protected static ?string $navigationLabel = 'Auditoria de Ações';
    protected static ?string $navigationGroup = 'Históricos';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 99;
    protected static ?string $slug = 'auditoria-acoes';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function getStats(): array
    {
        $q = AdminAuditLog::query();

        return [
            'total'  => (clone $q)->count(),
            'today'  => (clone $q)->whereDate('created_at', now()->toDateString())->count(),
            'admins' => (clone $q)->distinct('admin_id')->count('admin_id'),
            'latest' => optional((clone $q)->latest('created_at')->value('created_at'))?->format('d/m/Y H:i') ?? '-',
        ];
    }

    protected function actionLabels(): array
    {
        return [
            'wallet.edit'         => 'Editou saldo',
            'withdrawal.approve'  => 'Aprovou saque',
            'withdrawal.refund'   => 'Reembolsou saque',
        ];
    }

    public function table(Table $table): Table
    {
        $labels = $this->actionLabels();

        return $table
            ->deferLoading()
            ->query(AdminAuditLog::query())
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('admin_name')->label('Admin')->searchable()->weight('bold')
                    ->description(fn (AdminAuditLog $r) => 'id '.$r->admin_id),
                TextColumn::make('action')->label('Ação')->badge()
                    ->formatStateUsing(fn (string $state) => $labels[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'wallet.edit' => 'warning',
                        'withdrawal.approve' => 'success',
                        'withdrawal.refund' => 'danger',
                        default => 'gray',
                    })->searchable(),
                TextColumn::make('target_user_id')->label('Usuário afetado')
                    ->formatStateUsing(function ($state) {
                        if (! $state) return '-';
                        $u = User::find($state);
                        return $u ? ($u->name.' (#'.$state.')') : ('#'.$state);
                    })->searchable(),
                TextColumn::make('description')->label('Descrição')->wrap()->limit(80),
                TextColumn::make('before')->label('Antes')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '-')
                    ->limit(60)->tooltip(fn (AdminAuditLog $r) => $r->before ? json_encode($r->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null),
                TextColumn::make('after')->label('Depois')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '-')
                    ->limit(60)->tooltip(fn (AdminAuditLog $r) => $r->after ? json_encode($r->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null),
                TextColumn::make('ip')->label('IP')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')->label('Ação')->options([
                    'wallet.edit' => 'Editou saldo',
                    'withdrawal.approve' => 'Aprovou saque',
                    'withdrawal.refund' => 'Reembolsou saque',
                ]),
                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('de')->label('De'),
                        \Filament\Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['de'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['ate'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible);
    }
}
