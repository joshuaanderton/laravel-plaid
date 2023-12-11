<?php

namespace Ja\LaravelPlaid\Models;

use Ja\LaravelPlaid\Enums\PlaidConnectorEnvEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlaidConnector extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'access_token',
        'institution_name',
        'plaid_institution_id',
        'plaid_item_id',
        'plaid_transactions_cursor',
        'plaid_env',
    ];

    protected $casts = [
        'access_token' => 'encrypted:string',
        'plaid_env' => PlaidConnectorEnvEnum::class,
    ];

    protected $appends = ['plaid_accounts'];

    public function plaidAccounts(): Attribute
    {
        return new Attribute(
            get: fn () => $this->accounts
        );
    }

    public function scopeCurrentEnv(Builder $query): Builder
    {
        return $query->where('plaid_env', env('PLAID_ENV', null));
    }
}
