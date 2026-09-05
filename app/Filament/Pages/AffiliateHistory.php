<?php



namespace App\Filament\Pages;

use App\Models\AffiliateHistory as ModelsAffiliateHistory;
use App\Models\AffiliateLogs;
use App\Models\GamesKey;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;

use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DetailsActin;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

class AffiliateHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.affiliate';

    protected static ?string $title = 'Afiliados';
    protected static ?string $model = User::class;

    protected static ?string $slug = 'afiliado';


    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin'); 
    }
    
    public static function canView(): bool
    {
        return auth()->user()->hasRole('admin'); 
    }
    


    public ?array $data = [];


    public function table(Table $table): Table
    {  
        return $table
            ->deferLoading()
        ->query(self::$model::query())
        ->defaultSort('created_at', 'desc')

        ->columns([
            TextColumn::make("id")->label("ID"),
            TextColumn::make("name")->label("Nome")->searchable(),
            TextColumn::make("email")->label("Email")->searchable(),
            TextColumn::make("wallet.refer_rewards")->label("Saldo")->money("BRL")->sortable(),
            TextColumn::make("affiliateHistory.logs")->label("RevShare")->formatStateUsing(function($record) {
                $count = 0;
                $affiliates = ModelsAffiliateHistory::where("inviter", $record->id)->where("status", 1)->get();
                foreach($affiliates as $item){
                    if ($item->commission_type == "revshare") {
                        $count += $item->commission;
                    }
                }
                
                return "R$". number_format($count, 2, ".", ",");

            })->default(0)->sortable(query: function (Builder $query, string $direction): Builder {
                return $query->leftJoin('affiliate_histories', 'affiliate_histories.user_id', '=', 'users.id')
                ->orderByRaw("FIELD(affiliate_histories.commission_type, 'revshare') " . $direction)
                ->select('affiliate_histories.commission_type', 'affiliate_histories.commission', 'users.*');
            }),
            TextColumn::make("affiliateHistory.commission")->label("CPA")->formatStateUsing(function($record) {
                $count = 0;
                $affiliates = ModelsAffiliateHistory::where("inviter", $record->id)->where("status", 1)->get();
                foreach($affiliates as $item){
                    if ($item->commission_type == "cpa" ) {
                        $count += $item->commission;
                    }
                }
                
                return "R$". number_format($count, 2, ".", ",");
            })->default(0)->sortable(query: function (Builder $query, string $direction): Builder {
                return $query->leftJoin('affiliate_histories', 'affiliate_histories.user_id', '=', 'users.id')
                ->orderByRaw("FIELD(affiliate_histories.commission_type, 'cpa') " . $direction)
                ->select('affiliate_histories.commission_type', 'affiliate_histories.commission', 'users.*');
            }),
            TextColumn::make("inviter")->label("Afiliados")->formatStateUsing(function ($record) {
                return User::where("inviter", $record->id)->count();
            })->default(0),
            TextColumn::make("created_at")->label("Criado em")->dateTime()->sortable()

        ])->actions([
           Action::make('details')
                ->label('Detalhes')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading(fn ($record) => 'Detalhes do afiliado #' . $record->id)
                ->modalContent(fn ($record) => new \Illuminate\Support\HtmlString(
                    '<div style="display:grid;gap:8px;font-size:13px">' .
                    '<div><strong>Nome:</strong> ' . e($record->name ?? '-') . '</div>' .
                    '<div><strong>E-mail:</strong> ' . e($record->email ?? '-') . '</div>' .
                    '<div><strong>ID:</strong> ' . e((string) $record->id) . '</div>' .
                    '</div>'
                )),
          
            
        ]);



    
    }

 
}