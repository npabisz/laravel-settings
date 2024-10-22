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

        foreach ($definitions as $definition) {
            if ($definition['name'] === $name) {
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
