<?php



namespace App\Providers;

use App\Helpers\Core;
use App\Services\ThemeTokenService;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Js;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {

    }


    public function boot(): void
    {





        Schema::defaultStringLength(191);

        Builder::macro('whereLike', function ($attributes, string $searchTerm) {
            $this->where(function (Builder $query) use ($attributes, $searchTerm) {
                foreach (Arr::wrap($attributes) as $attribute) {
                    $query->when(
                        str_contains($attribute, '.'),
                        function (Builder $query) use ($attribute, $searchTerm) {
                            $buffer = explode('.', $attribute);
                            $attributeField = array_pop($buffer);
                            $relationPath = implode('.', $buffer);

                            $query->orWhereHas($relationPath, function (Builder $query) use ($attributeField, $searchTerm) {
                                $query->where($attributeField, 'LIKE', "%{$searchTerm}%");
                            });
                        },
                        function (Builder $query) use ($attribute, $searchTerm) {
                            $query->orWhere($attribute, 'LIKE', "%{$searchTerm}%");
                        }
                    );
                }
            });

            return $this;
        });


        Validator::extend('cpf_ou_cnpj', function ($attribute, $value, $parameters, $validator) {
            $num = Core::soNumero($value);

            return strlen($num) === 11 || strlen($num) === 14;
        }, 'O campo :attribute deve ser um CPF ou CNPJ válido.');


        View::composer('*', function ($view) {
            try {
                $themeCssVariables = app(ThemeTokenService::class)->toRootCss();
            } catch (\Throwable $e) {
                $themeCssVariables = ':root {}';
            }

            $view->with('themeCssVariables', $themeCssVariables);
        });
    }
}