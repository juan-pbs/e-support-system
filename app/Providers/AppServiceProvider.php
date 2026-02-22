<?php

namespace App\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Router $router): void
    {
        $router->aliasMiddleware('gerente', \App\Http\Middleware\Gerente::class);
        $router->aliasMiddleware('tecnico', \App\Http\Middleware\Tecnico::class);
        $router->aliasMiddleware('admin', \App\Http\Middleware\Admin::class);
    }
}
