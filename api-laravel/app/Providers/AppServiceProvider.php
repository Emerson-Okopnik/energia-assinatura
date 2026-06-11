<?php

namespace App\Providers;

use App\Domain\Faturamento\Contracts\LedgerRepository;
use App\Infrastructure\Faturamento\EloquentLedgerRepository;
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
        //
    }
}
