<?php

namespace App\Models;

use Npabisz\LaravelSettings\Models\AbstractSetting;

class Setting extends AbstractSetting
{
    /**
     * @return array
     */
    public static function getSettingsDefinitions (): array
    {
        return [
            /**
             * Global settings for you service,
             * these are not assigned to any model.
             */
            [

                'name' => 'example_setting',
                'default' => 'default_value',
                'options' => [
                    'default_value_2',
                    'default_value',
                ],
            ],
            [
                'name' => 'example_setting_2',
                'cast' => 'bool',
                'default' => false,
            ],
        ];
    }
}
