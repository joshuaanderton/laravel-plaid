<?php

namespace Ja\Plaid\Actions;

use App\Models\Account;
use App\Models\Category;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\PlaidConnector;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateTransaction
{
    use AsAction;

    public function handle(PlaidConnector $plaidConnector, array $transactionData): Transaction
    {
        $data = $transactionData;

        $transaction = $plaidConnector->transactions()->firstWhere(
            'plaid_connector_transaction.plaid_transaction_id',
            $data['transaction_id']
        );

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
            $merchant = Merchant::firstOrCreate(['name' => $data['merchant_name']]);
        }

        if ($data['category_id']) {
            $category = Category::firstWhere('plaid_category_id', $data['category_id']);
        }

        $transaction->update([
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
        ]);

        return $transaction;
    }
}
