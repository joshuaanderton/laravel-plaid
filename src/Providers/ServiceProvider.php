<?php

namespace Ja\LaravelPlaid\Providers;

use Illuminate\Support\Str;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Ja\LaravelPlaid\Http\Controllers\PlaidConnectorsController;
use Ja\LaravelPlaid\Http\Controllers\PlaidWebhooksController;

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
        Router::macro('plaid', function () {
            Route::resource('plaid_connectors', PlaidConnectorsController::class, ['except' => ['destroy']]);
            Route::post('plaid_connectors/{plaid_connector}/import', [PlaidConnectorsController::class, 'import'])->name('plaid_connectors.import');
            Route::put('plaid_connectors/{plaid_connector}/update-name', [PlaidConnectorsController::class, 'updateName'])->name('plaid_connectors.update-name');
            Route::resource('webhooks', PlaidWebhooksController::class);
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
