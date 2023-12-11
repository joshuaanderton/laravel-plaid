<?php

namespace Ja\Plaid\Providers;

use Illuminate\Support\Str;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Ja\Plaid\Http\Controllers\PlaidConnectorsController;
use Ja\Plaid\Http\Controllers\PlaidWebhooksController;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->registerRouterMacro();
    }

    public function boot()
    {
        //
    }

    protected function registerRouterMacro(): void
    {
        Router::macro('plaid', function (?string $as = 'plaid_connectors') {
            Route::resource($as, PlaidConnectorsController::class, ['except' => ['destroy']]);
            Route::resource('webhooks', PlaidWebhooksController::class, compact('as'));
            Route::put('plaid_connectors/update-name', [PlaidConnectorsController::class, 'updateName'])->name('plaid_connectors.update-name');
        });
    }

    private function path(string ...$path): string
    {
        return join('/', [
            Str::remove('src/Providers', __DIR__),
            ...$path
        ]);
    }
}
