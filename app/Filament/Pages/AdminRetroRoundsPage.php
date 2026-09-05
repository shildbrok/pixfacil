<?php

namespace App\Filament\Pages;

use App\Models\HouseGame;
use App\Models\HouseGameRound;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AdminRetroRoundsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-retro-rounds-page';
    protected static ?string $title = 'Rodadas Retrô';
    protected static ?string $navigationLabel = 'Rodadas Retrô';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $slug = 'rodadas-retro';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getStats(): array
    {
        return [
            'open' => HouseGameRound::where('status', HouseGameRound::STATUS_OPEN)->count(),
            'won' => HouseGameRound::where('status', HouseGameRound::STATUS_WON)->count(),
            'lost' => HouseGameRound::whereIn('status', [HouseGameRound::STATUS_LOST, HouseGameRound::STATUS_EXPIRED])->count(),
            'paid' => (float) HouseGameRound::where('status', HouseGameRound::STATUS_WON)->sum('payout'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(HouseGameRound::query()->with(['game', 'user']))
            ->deferLoading()
            ->columns([
                Tables\Columns\TextColumn::make('round_uuid')->label('Rodada')->limit(13)->copyable()->tooltip(fn (HouseGameRound $r): string => $r->round_uuid),
                Tables\Columns\TextColumn::make('user.name')->label('Jogador')->searchable()->description(fn (HouseGameRound $r): string => $r->user?->email ?: 'ID '.$r->user_id),
                Tables\Columns\TextColumn::make('game.name')->label('Jogo')->badge()->placeholder(fn (HouseGameRound $r): string => $r->game_slug),
                Tables\Columns\TextColumn::make('bet')->label('Aposta')->money('BRL'),
                Tables\Columns\TextColumn::make('payout')->label('Pagamento')->money('BRL')->color(fn ($state): string => (float) $state > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => [
                        'opening' => 'Abrindo', 'open' => 'Em jogo', 'settling' => 'Liquidando', 'won' => 'Ganhou',
                        'lost' => 'Perdeu', 'expired' => 'Expirou', 'canceled' => 'Cancelada', 'payout_failed' => 'Falha no pagamento',
                    ][$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'won' => 'success', 'open', 'settling' => 'warning', 'payout_failed' => 'danger', 'lost', 'expired', 'canceled' => 'gray', default => 'info',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Início')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('settled_at')->label('Fim')->dateTime('d/m/Y H:i:s')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('failure_reason')->label('Observação')->limit(40)->tooltip(fn (?string $state): ?string => $state)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('house_game_id')->label('Jogo')->options(fn (): array => HouseGame::orderBy('name')->pluck('name', 'id')->toArray()),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options([
                    'open' => 'Em jogo', 'won' => 'Ganhou', 'lost' => 'Perdeu', 'expired' => 'Expirou', 'payout_failed' => 'Falha no pagamento',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->striped();
    }
}
