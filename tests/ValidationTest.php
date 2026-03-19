<?php

namespace Npabisz\LaravelSettings\Tests;

use App\Models\Setting;
use Npabisz\LaravelSettings\Facades\Settings;
use Npabisz\LaravelSettings\SettingsContainer;
use Npabisz\LaravelSettings\Tests\Fixtures\User;

class ValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        //
    }

    protected function createUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'test-shop.myshopify.com',
            'email' => 'test@example.com',
        ], $attrs));
    }

    // ─── Setting name validation ────────────────────────────

    public function test_valid_setting_names(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $this->assertTrue($container->isValidSettingName('app_enabled'));
        $this->assertTrue($container->isValidSettingName('api_mode'));
        $this->assertTrue($container->isValidSettingName('api_key'));
        $this->assertTrue($container->isValidSettingName('currency'));
        $this->assertTrue($container->isValidSettingName('max_weight'));
    }

    public function test_invalid_setting_name(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $this->assertFalse($container->isValidSettingName('nonexistent'));
        $this->assertFalse($container->isValidSettingName(''));
        $this->assertFalse($container->isValidSettingName('API_MODE'));
    }

    public function test_get_invalid_name_throws(): void
    {
        $user = $this->createUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('setting definition does not exists');
        Settings::scope($user)->get('totally_invalid');
    }

    public function test_set_invalid_name_throws(): void
    {
        $user = $this->createUser();

        $this->expectException(\Exception::class);
        Settings::scope($user)->set('totally_invalid', 'value');
    }

    // ─── Setting value validation (options) ─────────────────

    public function test_valid_option_values(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $this->assertTrue($container->isValidSettingValue('api_mode', 'production'));
        $this->assertTrue($container->isValidSettingValue('api_mode', 'sandbox'));
    }

    public function test_invalid_option_value(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $this->assertFalse($container->isValidSettingValue('api_mode', 'staging'));
        $this->assertFalse($container->isValidSettingValue('api_mode', ''));
        $this->assertFalse($container->isValidSettingValue('api_mode', 'PRODUCTION'));
    }

    public function test_set_invalid_option_value_throws(): void
    {
        $user = $this->createUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid setting value');
        Settings::scope($user)->set('api_mode', 'invalid');
    }

    public function test_setting_without_options_accepts_any_value(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $this->assertTrue($container->isValidSettingValue('api_key', 'anything'));
        $this->assertTrue($container->isValidSettingValue('api_key', ''));
        $this->assertTrue($container->isValidSettingValue('api_key', '123'));
    }

    // ─── Setting options retrieval ──────────────────────────

    public function test_get_setting_options(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $options = $container->getSettingOptions('api_mode');
        $this->assertEquals(['production', 'sandbox'], $options);
    }

    public function test_get_setting_options_empty_when_no_options(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $options = $container->getSettingOptions('api_key');
        $this->assertEquals([], $options);
    }

    // ─── HasSettings trait validation ───────────────────────

    public function test_model_without_has_settings_trait_throws(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('have to use');

        // stdClass doesn't use HasSettings trait
        $model = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'users';
        };

        new SettingsContainer($model);
    }
}
