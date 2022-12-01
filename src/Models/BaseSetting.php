<?php

namespace Npabisz\LaravelSettings\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseSetting
{
    /**
     * @param array $data
     */
    abstract public function fromArray (array $data);

    /**
     * @return array
     */
    abstract public function toArray (): array;

    /**
     * Cast the given value.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     *
     * @return $this
     */
    public function get (Model $model, string $key, mixed $value, array $attributes)
    {
        $instance = new static;
        $instance->fromArray(json_decode($value, true) ?? []);

        return $instance;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param Model $model
     * @param string $key
     * @param self $value
     * @param array $attributes
     *
     * @return array
     */
    public function set (Model $model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return [$key => null];
        }

        if (!$value instanceof static) {
            throw new \Exception('The given value is not an ' . static::class . ' instance.');
        }

        return [$key => $value->__toString()];
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
