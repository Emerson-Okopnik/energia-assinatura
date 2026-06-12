<?php

namespace App\Providers;

use App\Domain\Faturamento\Contracts\LedgerRepository;
use App\Infrastructure\Faturamento\EloquentLedgerRepository;
use App\Support\Format;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LedgerRepository::class, EloquentLedgerRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Diretivas de UNIDADE padronizadas para o PDF (REGRAS_DE_CALCULO.md §1).
        // Toda a formatação vive em App\Support\Format (DRY); o Blade só chama.
        Blade::directive('kwh', fn (string $expr) => "<?php echo \\App\\Support\\Format::kwh($expr); ?>");
        Blade::directive('reais', fn (string $expr) => "<?php echo \\App\\Support\\Format::reais($expr); ?>");
        Blade::directive('tarifa', fn (string $expr) => "<?php echo \\App\\Support\\Format::tarifa($expr); ?>");
        Blade::directive('percentual', fn (string $expr) => "<?php echo \\App\\Support\\Format::percentual($expr); ?>");
        Blade::directive('numero', fn (string $expr) => "<?php echo \\App\\Support\\Format::numero($expr); ?>");
    }
}
