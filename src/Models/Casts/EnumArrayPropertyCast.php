<?php

namespace Ja\Plaid\Models\Casts;

use Exception;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Str;

class EnumArrayPropertyCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes): string
    {
        $mapProperty = $this->getEnumPropertyName($key);
        $map = $model->$mapProperty;

        return $map[$value];
    }

    public function set($model, $key, $value, $attributes)
    {
        $modelName = get_class($model);
        $mapProperty = $this->getEnumPropertyName($key);
        $map = $model->$mapProperty;

        if (is_int($value) && isset($map[$value])) {
            return $value;
        } elseif (is_string($value) && in_array($value, $map)) {
            return array_search($value, $map);
        }

        throw new Exception("Invalid enum value for {$key} field on {$modelName} model.");
    }

    public function getEnumPropertyName(string $key): string
    {
        return Str::camel(Str::plural($key));
    }
}
