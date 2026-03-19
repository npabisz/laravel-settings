<?php

namespace Npabisz\LaravelSettings\Tests;

use Npabisz\LaravelSettings\Facades\Settings;
use Npabisz\LaravelSettings\Tests\Fixtures\ApiModeEnum;
use Npabisz\LaravelSettings\Tests\Fixtures\SenderSetting;
use Npabisz\LaravelSettings\Tests\Fixtures\User;

class ExtendedCastingTest extends TestCase
{
    protected function createUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'test-shop.myshopify.com',
            'email' => 'test@example.com',
        ], $attrs));
    }

    // ─── Name only (no default, no cast) ────────────────────

    public function test_name_only_setting_defaults_to_null(): void
    {
        $user = $this->createUser();

        $this->assertNull(Settings::scope($user)->get('shop_notes'));
    }

    public function test_name_only_setting_set_and_get(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('shop_notes', 'Some notes here');
        $this->assertEquals('Some notes here', Settings::scope($user)->get('shop_notes'));
    }

    public function test_name_only_setting_persists(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('shop_notes', 'Persisted');
        Settings::clearScope($user);

        $this->assertEquals('Persisted', Settings::scope($user)->get('shop_notes'));
    }

    // ─── Integer cast ───────────────────────────────────────

    public function test_integer_default(): void
    {
        $user = $this->createUser();

        $value = Settings::scope($user)->get('orders_count');
        $this->assertSame(0, $value);
    }

    public function test_integer_set_and_get(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('orders_count', 42);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('orders_count');
        $this->assertSame(42, $value);
    }

    public function test_integer_stored_as_string_cast_back(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('orders_count', 100);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('orders_count');
        $this->assertIsInt($value);
        $this->assertSame(100, $value);
    }

    // ─── Bool default false ─────────────────────────────────

    public function test_bool_default_false(): void
    {
        $user = $this->createUser();

        $this->assertFalse(Settings::scope($user)->get('maintenance_mode'));
    }

    public function test_bool_toggle(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('maintenance_mode', true);
        $this->assertTrue(Settings::scope($user)->get('maintenance_mode'));

        Settings::scope($user)->set('maintenance_mode', false);
        $this->assertFalse(Settings::scope($user)->get('maintenance_mode'));
    }

    // ─── Array/JSON cast ────────────────────────────────────

    public function test_array_default_is_raw_string(): void
    {
        $user = $this->createUser();

        // Default is returned as-is (raw JSON string) when not persisted
        // because there's no DB roundtrip to trigger the Eloquent cast
        $value = Settings::scope($user)->get('allowed_countries');
        $this->assertEquals('["PL"]', $value);
    }

    public function test_array_persisted_is_cast(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('allowed_countries', ['PL', 'DE']);
        Settings::clearScope($user);

        // After persistence + retrieval, the Eloquent cast applies
        $value = Settings::scope($user)->get('allowed_countries');
        $this->assertIsArray($value);
        $this->assertEquals(['PL', 'DE'], $value);
    }

    public function test_array_set_and_get(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('allowed_countries', ['PL', 'DE', 'CZ']);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('allowed_countries');
        $this->assertIsArray($value);
        $this->assertEquals(['PL', 'DE', 'CZ'], $value);
    }

    public function test_array_empty(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('allowed_countries', []);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('allowed_countries');
        $this->assertIsArray($value);
        $this->assertEmpty($value);
    }

    // ─── BaseSetting class cast ─────────────────────────────

    public function test_class_cast_default_returns_instance(): void
    {
        $user = $this->createUser();

        // With returnDefaultCast=true (default), BaseSetting cast returns instance
        $value = Settings::scope($user)->get('sender_info');
        $this->assertInstanceOf(SenderSetting::class, $value);
        $this->assertEquals('', $value->name);
        $this->assertEquals('', $value->email);
    }

    public function test_class_cast_default_raw_returns_string(): void
    {
        $user = $this->createUser();

        // With returnDefaultCast=false, returns raw default string
        $value = Settings::scope($user)->get('sender_info', null, false);
        $this->assertIsString($value);
    }

    public function test_class_cast_null_value_returns_empty_instance(): void
    {
        // BaseSetting with null value returns an empty instance (not null)
        // because BaseSetting::get() does fromArray(json_decode(null) ?? [])
        $setting = new \App\Models\Setting();
        $setting->setRawAttributes([
            'settingable_id' => null,
            'settingable_type' => null,
            'name' => 'sender_info',
            'value' => null,
        ]);

        $value = $setting->value;
        $this->assertInstanceOf(SenderSetting::class, $value);
        $this->assertEquals('', $value->name);
    }

    public function test_class_cast_set_with_instance(): void
    {
        $user = $this->createUser();

        $sender = new SenderSetting();
        $sender->fromArray([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+48123456789',
            'company' => 'Acme Inc',
        ]);

        Settings::scope($user)->set('sender_info', $sender);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('sender_info');
        $this->assertInstanceOf(SenderSetting::class, $value);
        $this->assertEquals('John Doe', $value->name);
        $this->assertEquals('john@example.com', $value->email);
        $this->assertEquals('+48123456789', $value->phone);
        $this->assertEquals('Acme Inc', $value->company);
    }

    public function test_class_cast_set_with_array(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('sender_info', [
            'name' => 'Jane',
            'email' => 'jane@test.com',
            'phone' => '',
            'company' => '',
        ]);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('sender_info');
        $this->assertInstanceOf(SenderSetting::class, $value);
        $this->assertEquals('Jane', $value->name);
        $this->assertEquals('jane@test.com', $value->email);
    }

    public function test_class_cast_stored_as_json(): void
    {
        $user = $this->createUser();

        $sender = new SenderSetting();
        $sender->fromArray(['name' => 'Test', 'email' => 'test@test.com', 'phone' => '', 'company' => '']);

        Settings::scope($user)->set('sender_info', $sender);

        $raw = \App\Models\Setting::where('settingable_id', $user->id)
            ->where('name', 'sender_info')
            ->first();

        $decoded = json_decode($raw->getRawOriginal('value'), true);
        $this->assertEquals('Test', $decoded['name']);
        $this->assertEquals('test@test.com', $decoded['email']);
    }

    // ─── BackedEnum cast ────────────────────────────────────

    public function test_enum_default_is_raw_string(): void
    {
        $user = $this->createUser();

        // Default is returned as raw string when not persisted
        $value = Settings::scope($user)->get('api_mode_enum');
        $this->assertEquals('production', $value);
    }

    public function test_enum_persisted_is_cast(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_mode_enum', ApiModeEnum::Sandbox);
        Settings::clearScope($user);

        // After DB roundtrip, Eloquent cast applies
        $value = Settings::scope($user)->get('api_mode_enum');
        $this->assertInstanceOf(ApiModeEnum::class, $value);
        $this->assertEquals(ApiModeEnum::Sandbox, $value);
    }

    public function test_enum_set_with_enum_instance(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_mode_enum', ApiModeEnum::Sandbox);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('api_mode_enum');
        $this->assertInstanceOf(ApiModeEnum::class, $value);
        $this->assertEquals(ApiModeEnum::Sandbox, $value);
    }

    public function test_enum_set_with_string_value(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_mode_enum', 'sandbox');
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('api_mode_enum');
        $this->assertInstanceOf(ApiModeEnum::class, $value);
        $this->assertEquals(ApiModeEnum::Sandbox, $value);
    }

    public function test_enum_options_auto_generated(): void
    {
        $user = $this->createUser();

        $options = Settings::scope($user)->getSettingOptions('api_mode_enum');
        $this->assertEquals(['production', 'sandbox'], $options);
    }

    public function test_enum_invalid_value_rejected(): void
    {
        $user = $this->createUser();

        $this->assertFalse(Settings::scope($user)->isValidSettingValue('api_mode_enum', 'invalid'));
    }

    public function test_enum_valid_values_accepted(): void
    {
        $user = $this->createUser();

        $this->assertTrue(Settings::scope($user)->isValidSettingValue('api_mode_enum', 'production'));
        $this->assertTrue(Settings::scope($user)->isValidSettingValue('api_mode_enum', 'sandbox'));
    }

    // ─── Nullable with is_nullable ──────────────────────────

    public function test_nullable_setting_default(): void
    {
        $user = $this->createUser();

        $this->assertNull(Settings::scope($user)->get('webhook_url'));
    }

    public function test_nullable_setting_set_and_get(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('webhook_url', 'https://example.com/hook');
        $this->assertEquals('https://example.com/hook', Settings::scope($user)->get('webhook_url'));
    }

    public function test_nullable_setting_set_to_null(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('webhook_url', 'https://example.com/hook');
        Settings::scope($user)->set('webhook_url', null);
        Settings::clearScope($user);

        $this->assertNull(Settings::scope($user)->get('webhook_url'));
    }

    // ─── allWithDefaults includes new types ─────────────────

    public function test_all_with_defaults_includes_all_definitions(): void
    {
        $user = $this->createUser();

        $all = Settings::scope($user)->allWithDefaults();
        $this->assertCount(12, $all);

        $names = $all->pluck('name')->toArray();
        $this->assertContains('shop_notes', $names);
        $this->assertContains('orders_count', $names);
        $this->assertContains('maintenance_mode', $names);
        $this->assertContains('allowed_countries', $names);
        $this->assertContains('sender_info', $names);
        $this->assertContains('api_mode_enum', $names);
        $this->assertContains('webhook_url', $names);
    }

    // ─── Validation for new types ───────────────────────────

    public function test_name_only_is_valid_name(): void
    {
        $user = $this->createUser();

        $this->assertTrue(Settings::scope($user)->isValidSettingName('shop_notes'));
    }

    public function test_name_only_accepts_any_value(): void
    {
        $user = $this->createUser();

        $this->assertTrue(Settings::scope($user)->isValidSettingValue('shop_notes', 'anything'));
        $this->assertTrue(Settings::scope($user)->isValidSettingValue('shop_notes', ''));
        $this->assertTrue(Settings::scope($user)->isValidSettingValue('shop_notes', '12345'));
    }
}
