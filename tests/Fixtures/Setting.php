<?php

namespace App\Models;

use Npabisz\LaravelSettings\Models\AbstractSetting;
use Npabisz\LaravelSettings\Tests\Fixtures\ApiModeEnum;
use Npabisz\LaravelSettings\Tests\Fixtures\SenderSetting;

class Setting extends AbstractSetting
{
    public static function getSettingsDefinitions(): array
    {
        return [
            [
                'name' => 'app_enabled',
                'cast' => 'bool',
                'default' => true,
            ],
            [
                'name' => 'api_mode',
                'default' => 'production',
                'options' => ['production', 'sandbox'],
            ],
            [
                'name' => 'api_key',
                'default' => null,
            ],
            [
                'name' => 'currency',
                'default' => 'PLN',
            ],
            [
                'name' => 'max_weight',
                'cast' => 'float',
                'default' => 25.0,
            ],
            [
                'name' => 'shop_notes',
            ],
            [
                'name' => 'orders_count',
                'cast' => 'integer',
                'default' => 0,
            ],
            [
                'name' => 'maintenance_mode',
                'cast' => 'bool',
                'default' => false,
            ],
            [
                'name' => 'allowed_countries',
                'cast' => 'array',
                'default' => '["PL"]',
            ],
            [
                'name' => 'sender_info',
                'cast' => SenderSetting::class,
                'default' => '{"name":"","email":"","phone":"","company":""}',
            ],
            [
                'name' => 'api_mode_enum',
                'enum' => ApiModeEnum::class,
                'default' => 'production',
            ],
            [
                'name' => 'webhook_url',
                'default' => null,
                'is_nullable' => true,
            ],
        ];
    }
}
