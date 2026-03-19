<?php

namespace Npabisz\LaravelSettings\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Npabisz\LaravelSettings\Traits\HasSettings;

/**
 * A different model type that also uses HasSettings.
 * Used to test multi-model isolation.
 */
class Shop extends Model
{
    use HasSettings;

    protected $table = 'users'; // reuse the users table for simplicity
    protected $guarded = [];

    public static function getSettingsDefinitions(): array
    {
        return [
            [
                'name' => 'shop_name',
                'default' => 'My Shop',
            ],
            [
                'name' => 'shop_theme',
                'default' => 'default',
                'options' => ['default', 'dark', 'light'],
            ],
        ];
    }
}
