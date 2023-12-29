<?php

namespace Ja\LaravelPlaid\Actions;

use App\Models\PlaidConnector;
use Illuminate\Console\Command;
use Ja\LaravelPlaid\Actions\ImportTransactions;
use Lorisleiva\Actions\Concerns\AsAction;

class QueueImportTransactions
{
    use AsAction;

    public string $commandSignature = 'laravel-plaid:import';

    public function handle(): void
    {
        PlaidConnector::get()->map(fn ($plaidConnector) => (
            ImportTransactions::dispatch($plaidConnector)
        ));
    }

    public function asCommand(Command $command): void
    {
        $this->handle();

        $command->info('Queued imports for all plaid connector accounts');
    }
}
