<?php

namespace Npabisz\LaravelSettings\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Npabisz\LaravelSettings\Traits\HasSettings;

class User extends Authenticatable
{
    use HasSettings;

    protected $guarded = [];

    public static function getSettingsDefinitions(): array
    {
        return [
            // Bool with default, explicitly non-nullable
            [
                'name' => 'app_enabled',
                'cast' => 'bool',
                'default' => true,
                'is_nullable' => false,
            ],
            // String with options
            [
                'name' => 'api_mode',
                'default' => 'production',
                'options' => ['production', 'sandbox'],
            ],
            // Nullable string, default null
            [
                'name' => 'api_key',
                'default' => null,
            ],
            // String with default
            [
                'name' => 'currency',
                'default' => 'PLN',
            ],
            // Float with default
            [
                'name' => 'max_weight',
                'cast' => 'float',
                'default' => 25.0,
            ],
            // Name only — no default, no cast, no options
            [
                'name' => 'shop_notes',
            ],
            // Integer cast
            [
                'name' => 'orders_count',
                'cast' => 'integer',
                'default' => 0,
            ],
            // Bool default false
            [
                'name' => 'maintenance_mode',
                'cast' => 'bool',
                'default' => false,
            ],
            // JSON/array cast
            [
                'name' => 'allowed_countries',
                'cast' => 'array',
                'default' => '["PL"]',
            ],
            // Cast to BaseSetting subclass (complex object)
            [
                'name' => 'sender_info',
                'cast' => SenderSetting::class,
                'default' => '{"name":"","email":"","phone":"","company":""}',
            ],
            // BackedEnum setting
            [
                'name' => 'api_mode_enum',
                'enum' => ApiModeEnum::class,
                'default' => 'production',
            ],
            // Nullable with is_nullable flag
            [
                'name' => 'webhook_url',
                'default' => null,
                'is_nullable' => true,
            ],
        ];
    }
}
