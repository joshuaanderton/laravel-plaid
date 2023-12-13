<?php

namespace Ja\LaravelPlaid\Actions;

use App\Models\Account;
use App\Models\PlaidConnector;
use Exception;
use Illuminate\Support\Str;
use TomorrowIdeas\Plaid\Plaid;
use Lorisleiva\Actions\Concerns\AsAction;

class ImportAccounts
{
    use AsAction;

    public function handle(PlaidConnector $plaidConnector): array
    {
        if ($plaidConnector->requires_reconnect) {
            return [];
        }

        $plaid = new Plaid(
            env('PLAID_CLIENT_ID'),
            env('PLAID_SECRET_KEY'),
            env('PLAID_ENV')
        );

        $existingAccounts = $plaidConnector->accounts()->pluck('plaid_account_id');

        // $linkToken = $plaidConnector->getLinkToken();

        $response = $plaid->accounts->getBalance(
            access_token: $plaidConnector->access_token,
            options: array_filter([
                'account_ids' => $existingAccounts->count() > 0 ? $existingAccounts : null
            ])
        );

        $accounts = collect($response->accounts)->map(function ($plaidAccount) use ($plaidConnector) {

            $account = $plaidConnector->accounts()->firstWhere('plaid_connector_account.plaid_account_id', $plaidAccount->account_id);

            $data = [
                'team_id' => $plaidConnector->team_id,
                'balance_currency' => $plaidAccount->balances->iso_currency_code,
                'balance_available' => $plaidAccount->balances->available,
                'balance_current' => $plaidAccount->balances->current,
                'balance_limit' => $plaidAccount->balances->limit,
                'balance_pending' => $plaidAccount->balances->pending ?? 0,
                'mask' => $plaidAccount->mask,
                'name' => $plaidAccount->name,
                'type' => $plaidAccount->type,
                'subtype' => Str::slug($plaidAccount->subtype, '_'),
            ];

            if ($account) {
                $account->update($data);
            } else {
                $account = Account::create(array_merge($data, [
                    'name' => $plaidAccount->name,
                ]));

                $plaidConnector->accounts()->attach($account->id, [
                    'plaid_account_id' => $plaidAccount->account_id,
                    'name' => $plaidAccount->official_name,
                ]);
            }

            return $account;
        })->all();

        ImportTransactions::dispatch($plaidConnector);

        return $accounts;
    }
}
