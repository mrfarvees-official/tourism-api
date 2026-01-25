<?php

namespace App\Providers;

use App\Services\EnvironmentStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EnvironmentStore::class, fn () => new EnvironmentStore());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
