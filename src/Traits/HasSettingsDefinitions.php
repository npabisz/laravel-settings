<?php

namespace Npabisz\LaravelSettings\Traits;

trait HasSettingsDefinitions
{
    /**
     * @param string $name
     *
     * @return ?array
     */
    public static function getSettingDefinition (\BackedEnum|string $name): ?array
    {
        $definitions = static::getSettingsDefinitions();
        $stringName = $name instanceof \BackedEnum
            ? $name->value
            : $name;

        foreach ($definitions as $definition) {
            $definitionName = $definition['name'] instanceof \BackedEnum
                ? $definition['name']->value
                : $definition['name'];

            if ($definitionName === $stringName) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    abstract public static function getSettingsDefinitions(): array;
}
