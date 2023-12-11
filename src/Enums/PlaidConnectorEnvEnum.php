<?php

namespace Ja\LaravelPlaid\Enums;

enum PlaidConnectorEnvEnum: int
{
    case Development = 0;
    case Production = 1;
    case Sandbox = 2;

    public static function current(): int
    {
        return match (env('PLAID_ENV')) {
            'development' => self::Development->value,
            'production' => self::Production->value,
            'sandbox' => self::Sandbox->value,
        };
    }
}
