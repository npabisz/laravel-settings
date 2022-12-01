<?php

namespace Npabisz\LaravelSettings\Traits;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Support\Collection as BaseCollection;

trait CastsToType
{
    /**
     * @param string $type
     *
     * @return string
     */
    protected function getCastTypeFromType (string $type) : string
    {
        if ($this->isCustomDateTimeCast($type)) {
            $convertedCastType = 'custom_datetime';
        } elseif ($this->isImmutableCustomDateTimeCast($type)) {
            $convertedCastType = 'immutable_custom_datetime';
        } elseif ($this->isDecimalCast($type)) {
            $convertedCastType = 'decimal';
        } else {
            $convertedCastType = trim(strtolower($type));
        }

        return $convertedCastType;
    }

    /**
     * @param  string  $castType
     *
     * @return bool
     */
    protected function isEnumCastableByType (string $castType): bool
    {
        if (in_array($castType, static::$primitiveCastTypes)) {
            return false;
        }

        if (function_exists('enum_exists') && enum_exists($castType)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $castType
     * @param mixed $value
     *
     * @return \BackedEnum|mixed|\UnitEnum|void
     */
    protected function getEnumCastableAttributeValueByType (string $castType, mixed $value)
    {
        if (is_null($value)) {
            return;
        }

        if ($value instanceof $castType) {
            return $value;
        }

        return $this->getEnumCaseFromValue($castType, $value);
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    protected function isClassCastableByType (string $type)
    {
        $castType = $this->parseCasterClass($type);

        if (in_array($castType, static::$primitiveCastTypes)) {
            return false;
        }

        if (class_exists($castType)) {
            return true;
        }

        throw new InvalidCastException($this->getModel(), 'value', $castType);
    }

    /**
     * @param  string $castType
     *
     * @return mixed
     */
    protected function resolveCasterClassByType (string $castType): mixed
    {
        $arguments = [];

        if (is_string($castType) && str_contains($castType, ':')) {
            $segments = explode(':', $castType, 2);

            $castType = $segments[0];
            $arguments = explode(',', $segments[1]);
        }

        if (is_subclass_of($castType, Castable::class)) {
            $castType = $castType::castUsing($arguments);
        }

        if (is_object($castType)) {
            return $castType;
        }

        return new $castType(...$arguments);
    }

    /**
     * @param string $type
     * @param mixed $value
     *
     * @return mixed
     */
    protected function getClassCastableAttributeValueByType (string $type, mixed $value): mixed
    {
        $caster = $this->resolveCasterClassByType($type);

        $value = $caster instanceof CastsInboundAttributes
            ? $value
            : $caster->get($this, 'value', $value, $this->attributes);

        return $value;
    }

    /**
     * @param string $type
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    protected function setEnumCastableAttributeByType(string $type, string $key, mixed $value): void
    {
        $enumClass = $type;

        if (! isset($value)) {
            $this->attributes[$key] = null;
        } elseif (is_object($value)) {
            $this->attributes[$key] = $this->getStorableEnumValue($value);
        } else {
            $this->attributes[$key] = $this->getStorableEnumValue(
                $this->getEnumCaseFromValue($enumClass, $value)
            );
        }
    }

    /**
     * @param string $type
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    protected function setClassCastableAttributeByType (string $type, string $key, mixed $value): void
    {
        $caster = $this->resolveCasterClassByType($type);

        $this->attributes = array_merge(
            $this->attributes,
            $this->normalizeCastClassResponse($key, $caster->set(
                $this, $key, $value, $this->attributes
            ))
        );

        if ($caster instanceof CastsInboundAttributes || ! is_object($value)) {
            unset($this->classCastCache[$key]);
        } else {
            $this->classCastCache[$key] = $value;
        }
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    protected function isJsonCastableByType (string $type): bool
    {
        return in_array($type, ['array', 'json', 'object', 'collection', 'encrypted:array', 'encrypted:collection', 'encrypted:json', 'encrypted:object'], true);
    }

    /**
     * @param string $type
     * @param mixed $value
     *
     * @return mixed
     */
    protected function castToType (string $type, mixed $value): mixed
    {
        $castType = $this->getCastTypeFromType($type);

        if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
            return $value;
        }

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $type, 2)[1]);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new BaseCollection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'immutable_date':
                return $this->asDate($value)->toImmutable();
            case 'immutable_custom_datetime':
            case 'immutable_datetime':
                return $this->asDateTime($value)->toImmutable();
            case 'timestamp':
                return $this->asTimestamp($value);
        }

        if ($this->isEnumCastableByType($type)) {
            return $this->getEnumCastableAttributeValueByType($type, $value);
        }

        if ($this->isClassCastableByType($type)) {
            return $this->getClassCastableAttributeValueByType($type, $value);
        }

        return $value;
    }

    /**
     * @param string $type
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    public function setAttributeByType (string $type, string $key, mixed $value): mixed
    {
        if ($this->isEnumCastableByType($type)) {
            $this->setEnumCastableAttributeByType($type, $key, $value);

            return $this;
        }

        if ($this->isClassCastableByType($type)) {
            $this->setClassCastableAttributeByType($type, $key, $value);

            return $this;
        }

        if (!is_null($value) && $this->isJsonCastableByType($type)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        if (str_contains($key, '->')) {
            return $this->fillJsonAttribute($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }
}
