<?php

namespace Ja\LaravelPlaid\Actions;

use Ja\LaravelPlaid\Enums\CurrencyEnum;
use App\Enums\TransactionCategoryConfidenceLevelEnum;
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

/**
 * @resource https://plaid.com/docs/api/products/transactions
 */
class CreateTransaction
{
    use AsAction;

    public function handle(PlaidConnector $plaidConnector, array $data): Transaction
    {
        $plaidConnectorAccount = PlaidConnectorAccount::firstWhere('plaid_account_id', $data['account_id']);
        $category = $merchant = $location = null;

        if ($plaidConnector->transactions()->where('plaid_connector_transaction.plaid_transaction_id', $data['transaction_id'])->exists()) {
            return UpdateTransaction::run($plaidConnector, $data);
        }

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
        }

        if ($data['merchant_name'] ?? false) {
            $merchantData = collect($data['counterparties'])->firstWhere('entity_id', $data['merchant_entity_id']);
            $merchant = Merchant::firstOrCreate(['name' => $data['merchant_name']], $merchantData ?? []);
        }

        if ($data['personal_finance_category'] ?? false) {
            $category = Category::firstWhere([
                'plaid_category_detailed' => $data['personal_finance_category']['detailed'],
            ]);
            $categoryConfidenceLevel = TransactionCategoryConfidenceLevelEnum::findByPlaidLevelKey(
                $data['personal_finance_category']['confidence_level'] ?? 'UNKNOWN'
            );
        }

        $team = $plaidConnector->team()->first();

        $transaction = $team->transactions()->create([
            'account_id' => $plaidConnectorAccount->account_id,
            'location_id' => $location?->id,
            'merchant_id' => $merchant?->id,
            'category_id' => $category?->id,
            'category_confidence_level' => $categoryConfidenceLevel,
            'name' => $data['name'],
            'description_original' => $data['original_description'] ?? null,
            'amount' => (float) $data['amount'],
            'payment_channel' => Str::snake($data['payment_channel']),
            'pending' => $data['pending'],
            'currency' => CurrencyEnum::from($data['iso_currency_code']),
            'transacted_at' => Carbon::parse($data['datetime'] ?? $data['date']),
            'authorized_at' => Carbon::parse($data['authorized_datetime'] ?? $data['authorized_date']),
            'plaid_transaction_snapshot' => $data,
        ]);

        $plaidConnector->transactions()->attach($transaction->id, [
            'plaid_transaction_id' => $data['transaction_id'],
            'plaid_connector_account_id' => $plaidConnectorAccount->id,
        ]);

        return $transaction;
    }
}
