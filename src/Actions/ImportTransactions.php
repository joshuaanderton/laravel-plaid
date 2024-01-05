<?php

namespace Ja\LaravelPlaid\Actions;

use App\Models\PlaidConnector;
use App\Services\PlaidTransactions;
use Exception;
use Ja\LaravelPlaid\Actions\CreateTransaction;
use Ja\LaravelPlaid\Actions\UpdateTransaction;
use Lorisleiva\Actions\Concerns\AsAction;

class ImportTransactions
{
    use AsAction;

    public function handle(PlaidConnector $plaidConnector): void
    {
        $response = (
            (new PlaidTransactions($plaidConnector->access_token))->get(
                cursor: $plaidConnector->plaid_transations_cursor,
                count: 500
            )
        );

        if ($response['error_code'] ?? null) {
            if (str($response['error_message'])->contains('the login details of this item have changed')) {
                $plaidConnector->update(['requires_reconnect' => true]);
                return;
            }

            throw new Exception($response['error_message']);
        }

        $added = collect(
            $response['added'] ?? []
        );

        if ($added->count() > 0) {
            $added->chunk(50)->map(fn ($chunk) => $chunk->map(fn ($data) => (
                CreateTransaction::dispatch($plaidConnector, $data)
            )));
        }

        $modified = collect(
            $response['modified'] ?? []
        );

        if ($modified->count() > 0) {
            $modified->chunk(50)->map(fn ($chunk) => $chunk->map(fn ($data) => (
                UpdateTransaction::dispatch($plaidConnector, $data)
            )));
        }

        $removed = collect(
            $response['removed'] ?? []
        );

        if ($removed->count() > 0) {
            $plaidConnector->transactions()->whereIn('plaid_transaction_id',
                $removed->all()
            )->delete();
        }

        if ($response['next_cursor'] ?? null) {
            $plaidConnector->update([
                'plaid_transactions_cursor' => $response['next_cursor'],
            ]);

            if ($response['has_more'] === true) {
                self::dispatch($plaidConnector);
            }
        }
    }
}
