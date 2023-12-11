<?php

namespace Ja\Plaid\Actions;

use App\Models\Account;
use App\Models\Category;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\PlaidConnector;
use App\Models\PlaidConnectorAccount;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateTransaction
{
    use AsAction;

    public function handle(PlaidConnector $plaidConnector, array $transactionData): Transaction
    {
        $data = $transactionData;
        $team = $plaidConnector->team()->first();
        $plaidConnectorAccount = PlaidConnectorAccount::firstWhere('plaid_account_id', $data['account_id']);

        if (count(array_unique(array_keys($data['location']))) > 1) {
            $location = Location::firstOrCreate(
                collect($data['location'])->only([
                    'address',
                    'city',
                    'region',
                    'country',
                ])->all(),
                $data['location']
            );
        }

        if ($data['merchant_name'] ?? false) {
            $merchantData = collect($data['counterparties'])->firstWhere('entity_id', $data['merchant_entity_id']);
            $merchant = Merchant::firstOrCreate(['name' => $data['merchant_name']], $merchantData ?? []);
        }

        if ($data['category_id']) {
            $category = Category::firstWhere('plaid_category_id', $data['category_id']);
        }

        $transaction = $team->transactions()->create([
            'account_id' => $plaidConnectorAccount->account_id,
            'location_id' => $location->id ?? null,
            'merchant_id' => $merchant->id ?? null,
            'category_id' => $category->id ?? null,
            'name' => $data['name'],
            'amount' => (float) $data['amount'],
            'payment_channel' => Str::snake($data['payment_channel']),
            'pending' => $data['pending'],
            'currency' => array_search($data['iso_currency_code'], Account::currencies),
            'transacted_at' => Carbon::parse($data['datetime'] ?? $data['date']),
            'authorized_at' => Carbon::parse($data['authorized_datetime'] ?? $data['authorized_date']),

            // UNUSED FIELDS
            // ---------------
            // 'unofficial_currency_code' => null,
            // 'check_number' => null,
            // 'pending_transaction_id' => null,
            // 'account_owner' => null,
            // 'transaction_code' => null,
            // 'category' => [
            //   'Service',
            //   'Utilities',
            //   'Electric'
            // ],
            // 'payment_meta' => [
            //   'by_order_of' => null,
            //   'payee' => null,
            //   'payer' => null,
            //   'payment_method' => null,
            //   'payment_processor' => null,
            //   'ppd_id' => null,
            //   'reason' => null,
            //   'reference_number' => null
            // ],

        ]);

        $plaidConnector->transactions()->attach($transaction->id, [
            'plaid_transaction_id' => $data['transaction_id'],
            'plaid_connector_account_id' => $plaidConnectorAccount->id,
        ]);

        return $transaction;
    }
}
