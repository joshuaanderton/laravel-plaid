<?php

namespace Ja\LaravelPlaid\Actions;

use App\Enums\CurrencyEnum;
use App\Enums\TransactionCategoryConfidenceLevelEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\PlaidConnector;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @resource https://plaid.com/docs/api/products/transactions
 */
class UpdateTransaction
{
    use AsAction;

    public function handle(PlaidConnector $plaidConnector, array $data): Transaction
    {
        $transaction = $plaidConnector->transactions()->firstWhere(
            'plaid_connector_transaction.plaid_transaction_id',
            $data['transaction_id']
        );

        $updateData = [];

        if (
            count($locationStr = array_unique(array_keys($data['location']))) > 1 &&
            ! empty(trim(implode('', $locationStr)))
        ) {
            $location = Location::firstOrCreate(
                collect($data['location'])->only([
                    'address',
                    'city',
                    'region',
                    'country',
                ])->all(),
                $data['location']
            );
            $updateData['location_id'] = $location->id;
        }

        if (! $transaction->merchant_id && $data['merchant_name'] ?? false) {
            $merchant = Merchant::firstOrCreate(['name' => $data['merchant_name']]);
            $updateData['merchant_id'] = $merchant->id;
        }

        if (! $transaction->category_id && $data['personal_finance_category'] ?? false) {
            $category = Category::firstWhere([
                'plaid_category_detailed' => $data['personal_finance_category']['detailed'],
            ]);
            $categoryConfidenceLevel = TransactionCategoryConfidenceLevelEnum::findByPlaidLevelKey(
                $data['personal_finance_category']['confidence_level'] ?? 'UNKNOWN'
            );
            $updateData['category_id'] = $category->id;
            $updateData['category_confidence_level'] = $categoryConfidenceLevel;
        }

        $transaction->update(array_merge($updateData, [
            'name' => $data['name'],
            'description_original' => $data['original_description'] ?? null,
            'amount' => (float) $data['amount'],
            'payment_channel' => Str::snake($data['payment_channel']),
            'pending' => $data['pending'],
            'currency' => CurrencyEnum::from($data['iso_currency_code']),
            'transacted_at' => Carbon::parse($data['datetime'] ?? $data['date']),
            'authorized_at' => Carbon::parse($data['authorized_datetime'] ?? $data['authorized_date']),
            'plaid_transaction_snapshot' => $data,
        ]));

        return $transaction;
    }
}
