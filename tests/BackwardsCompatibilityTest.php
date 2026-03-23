<?php

namespace Npabisz\LaravelSettings\Tests;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Npabisz\LaravelSettings\Facades\Settings;
use Npabisz\LaravelSettings\SettingsContainer;
use Npabisz\LaravelSettings\Tests\Fixtures\ApiModeEnum;
use Npabisz\LaravelSettings\Tests\Fixtures\SenderSetting;
use Npabisz\LaravelSettings\Tests\Fixtures\User;

/**
 * Verifies that all pre-existing behaviors are preserved after the scope caching
 * and bugfix changes. Tests every cast type, default handling, and storage format
 * to ensure production compatibility.
 */
class BackwardsCompatibilityTest extends TestCase
{
    protected function createUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'test-shop.myshopify.com',
            'email' => 'test@example.com',
        ], $attrs));
    }

    // ═══════════════════════════════════════════════════════════
    // CAST: bool
    // ═══════════════════════════════════════════════════════════

    public function test_bool_default_true_not_persisted(): void
    {
        $user = $this->createUser();
        $this->assertTrue(Settings::scope($user)->get('app_enabled'));
    }

    public function test_bool_default_false_not_persisted(): void
    {
        $user = $this->createUser();
        $this->assertFalse(Settings::scope($user)->get('maintenance_mode'));
    }

    public function test_bool_set_true_persisted(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('maintenance_mode', true);
        Settings::clearScope($user);
        $this->assertTrue(Settings::scope($user)->get('maintenance_mode'));
    }

    public function test_bool_set_false_persisted(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('app_enabled', false);
        Settings::clearScope($user);
        $this->assertFalse(Settings::scope($user)->get('app_enabled'));
    }

    public function test_bool_db_stores_correct_value(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('app_enabled', false);

        $raw = Setting::where('settingable_id', $user->id)
            ->where('name', 'app_enabled')
            ->first();

        $this->assertNotNull($raw);

        // When read back, must be bool false
        Settings::clearScope($user);
        $this->assertIsBool(Settings::scope($user)->get('app_enabled'));
        $this->assertFalse(Settings::scope($user)->get('app_enabled'));
    }

    public function test_bool_string_false_from_db_casts_correctly(): void
    {
        $user = $this->createUser();
        // Simulate raw "false" string in DB (issue #6)
        Setting::create([
            'settingable_id' => $user->id,
            'settingable_type' => User::class,
            'name' => 'app_enabled',
            'value' => 'false',
        ]);
        $this->assertFalse(Settings::scope($user)->get('app_enabled'));
    }

    public function test_bool_string_true_from_db_casts_correctly(): void
    {
        $user = $this->createUser();
        Setting::create([
            'settingable_id' => $user->id,
            'settingable_type' => User::class,
            'name' => 'app_enabled',
            'value' => 'true',
        ]);
        $this->assertTrue(Settings::scope($user)->get('app_enabled'));
    }

    public function test_bool_string_zero_from_db_casts_correctly(): void
    {
        $user = $this->createUser();
        Setting::create([
            'settingable_id' => $user->id,
            'settingable_type' => User::class,
            'name' => 'app_enabled',
            'value' => '0',
        ]);
        $this->assertFalse(Settings::scope($user)->get('app_enabled'));
    }

    public function test_bool_string_one_from_db_casts_correctly(): void
    {
        $user = $this->createUser();
        Setting::create([
            'settingable_id' => $user->id,
            'settingable_type' => User::class,
            'name' => 'app_enabled',
            'value' => '1',
        ]);
        $this->assertTrue(Settings::scope($user)->get('app_enabled'));
    }

    // ═══════════════════════════════════════════════════════════
    // CAST: integer
    // ═══════════════════════════════════════════════════════════

    public function test_integer_default_not_persisted(): void
    {
        $user = $this->createUser();
        $value = Settings::scope($user)->get('orders_count');
        $this->assertSame(0, $value);
    }

    public function test_integer_set_persisted(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('orders_count', 42);
        Settings::clearScope($user);
        $value = Settings::scope($user)->get('orders_count');
        $this->assertIsInt($value);
        $this->assertSame(42, $value);
    }

    public function test_integer_zero_persisted(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('orders_count', 0);
        Settings::clearScope($user);
        $value = Settings::scope($user)->get('orders_count');
        $this->assertSame(0, $value);
    }

    // ═══════════════════════════════════════════════════════════
    // CAST: float
    // ═══════════════════════════════════════════════════════════

    public function test_float_default_not_persisted(): void
    {
        $user = $this->createUser();
        $value = Settings::scope($user)->get('max_weight');
        $this->assertEquals(25.0, $value);
    }

    public function test_float_set_persisted(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('max_weight', 30.5);
        Settings::clearScope($user);
        $value = Settings::scope($user)->get('max_weight');
        $this->assertIsFloat($value);
        $this->assertEquals(30.5, $value);
    }

    // ═══════════════════════════════════════════════════════════
    // CAST: string (with options)
    // ═══════════════════════════════════════════════════════════

    public function test_string_with_options_default_not_persisted(): void
    {
        $user = $this->createUser();
        $this->assertEquals('production', Settings::scope($user)->get('api_mode'));
    }

    public function test_string_with_options_set_persisted(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_mode', 'sandbox');
        Settings::clearScope($user);
        $this->assertEquals('sandbox', Settings::scope($user)->get('api_mode'));
    }

    public function test_string_with_options_rejects_invalid(): void
    {
        $user = $this->createUser();
        $this->expectException(\Exception::class);
        Settings::scope($user)->set('api_mode', 'invalid');
    }

    // ═══════════════════════════════════════════════════════════
    // CAST: string (nullable, no options)
    // ═══════════════════════════════════════════════════════════

    public function test_nullable_string_default_null(): void
    {
        $user = $this->createUser();
        $this->assertNull(Settings::scope($user)->get('api_key'));
    }

    public function test_nullable_string_set_and_get(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', 'secret-key-123');
        Settings::clearScope($user);
        $this->assertEquals('secret-key-123', Settings::scope($user)->get('api_key'));
    }

    public function test_nullable_string_set_empty(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', '');
        Settings::clearScope($user);
        $this->assertEquals('', Settings::scope($user)->get('api_key'));
    }

    // ═══════════════════════════════════════════════════════════
    // NO CAST (name only, no default)
    // ═══════════════════════════════════════════════════════════

    public function test_no_cast_no_default_returns_null(): void
    {
        $user = $this->createUser();
        $this->assertNull(Settings::scope($user)->get('shop_notes'));
    }

    public function test_no_cast_set_and_get(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('shop_notes', 'hello world');
        Settings::clearScope($user);
        $this->assertEquals('hello world', Settings::scope($user)->get('shop_notes'));
    }

    // ═══════════════════════════════════════════════════════════
    // CAST: array
    // ═══════════════════════════════════════════════════════════

    public function test_array_persisted_roundtrip(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('allowed_countries', ['PL', 'DE', 'CZ']);
        Settings::clearScope($user);
        $value = Settings::scope($user)->get('allowed_countries');
        $this->assertIsArray($value);
        $this->assertEquals(['PL', 'DE', 'CZ'], $value);
    }

    public function test_array_empty_persisted(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('allowed_countries', []);
        Settings::clearScope($user);
        $value = Settings::scope($user)->get('allowed_countries');
        $this->assertIsArray($value);
        $this->assertEmpty($value);
    }

    public function test_array_db_stores_json(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('allowed_countries', ['PL', 'DE']);
        $raw = Setting::where('settingable_id', $user->id)
            ->where('name', 'allowed_countries')
            ->first()
            ->getRawOriginal('value');
        $this->assertEquals('["PL","DE"]', $raw);
    }

    // ═══════════════════════════════════════════════════════════
    // CAST: BaseSetting subclass (SenderSetting)
    // ═══════════════════════════════════════════════════════════

    public function test_class_cast_set_with_instance_roundtrip(): void
    {
        $user = $this->createUser();
        $sender = new SenderSetting();
        $sender->fromArray(['name' => 'John', 'email' => 'john@test.com', 'phone' => '+48111', 'company' => 'Acme']);
        Settings::scope($user)->set('sender_info', $sender);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('sender_info');
        $this->assertInstanceOf(SenderSetting::class, $value);
        $this->assertEquals('John', $value->name);
        $this->assertEquals('john@test.com', $value->email);
        $this->assertEquals('+48111', $value->phone);
        $this->assertEquals('Acme', $value->company);
    }

    public function test_class_cast_set_with_array_roundtrip(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('sender_info', [
            'name' => 'Jane', 'email' => 'jane@test.com', 'phone' => '', 'company' => 'Corp',
        ]);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('sender_info');
        $this->assertInstanceOf(SenderSetting::class, $value);
        $this->assertEquals('Jane', $value->name);
        $this->assertEquals('Corp', $value->company);
    }

    public function test_class_cast_db_stores_json(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('sender_info', [
            'name' => 'Test', 'email' => 'test@test.com', 'phone' => '', 'company' => '',
        ]);
        $raw = Setting::where('settingable_id', $user->id)
            ->where('name', 'sender_info')
            ->first()
            ->getRawOriginal('value');
        $decoded = json_decode($raw, true);
        $this->assertEquals('Test', $decoded['name']);
    }

    public function test_class_cast_default_returns_instance_via_returnDefaultCast(): void
    {
        $user = $this->createUser();
        // returnDefaultCast=true (default): should return SenderSetting instance from JSON default
        $value = Settings::scope($user)->get('sender_info');
        $this->assertInstanceOf(SenderSetting::class, $value);
    }

    public function test_class_cast_default_raw_returns_string(): void
    {
        $user = $this->createUser();
        // returnDefaultCast=false: should return raw default string
        $value = Settings::scope($user)->get('sender_info', null, false);
        $this->assertIsString($value);
    }

    public function test_class_cast_set_null_not_nullable_throws(): void
    {
        $user = $this->createUser();
        // app_enabled is marked is_nullable => false, so setting null should throw
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid setting value');
        Settings::scope($user)->set('app_enabled', null);
    }

    // ═══════════════════════════════════════════════════════════
    // CAST: BackedEnum
    // ═══════════════════════════════════════════════════════════

    public function test_enum_persisted_roundtrip_with_instance(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_mode_enum', ApiModeEnum::Sandbox);
        Settings::clearScope($user);
        $value = Settings::scope($user)->get('api_mode_enum');
        $this->assertInstanceOf(ApiModeEnum::class, $value);
        $this->assertEquals(ApiModeEnum::Sandbox, $value);
    }

    public function test_enum_persisted_roundtrip_with_string(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_mode_enum', 'sandbox');
        Settings::clearScope($user);
        $value = Settings::scope($user)->get('api_mode_enum');
        $this->assertInstanceOf(ApiModeEnum::class, $value);
        $this->assertEquals(ApiModeEnum::Sandbox, $value);
    }

    public function test_enum_default_not_persisted(): void
    {
        $user = $this->createUser();
        // Non-persisted enum default returns raw string (no DB roundtrip = no Eloquent cast)
        $value = Settings::scope($user)->get('api_mode_enum');
        $this->assertEquals('production', $value);
    }

    public function test_enum_db_stores_string_value(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_mode_enum', ApiModeEnum::Sandbox);
        $raw = Setting::where('settingable_id', $user->id)
            ->where('name', 'api_mode_enum')
            ->first()
            ->getRawOriginal('value');
        $this->assertEquals('sandbox', $raw);
    }

    // ═══════════════════════════════════════════════════════════
    // Nullable with is_nullable flag
    // ═══════════════════════════════════════════════════════════

    public function test_is_nullable_accepts_null(): void
    {
        $user = $this->createUser();
        $this->assertTrue(Settings::scope($user)->isValidSettingValue('webhook_url', null));
    }

    public function test_is_nullable_set_null_roundtrip(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('webhook_url', 'https://hook.example.com');
        Settings::scope($user)->set('webhook_url', null);
        Settings::clearScope($user);
        $this->assertNull(Settings::scope($user)->get('webhook_url'));
    }

    // ═══════════════════════════════════════════════════════════
    // get() default parameter behavior
    // ═══════════════════════════════════════════════════════════

    public function test_get_without_default_returns_definition_default(): void
    {
        $user = $this->createUser();
        $this->assertEquals('PLN', Settings::scope($user)->get('currency'));
    }

    public function test_get_with_null_default_returns_definition_default(): void
    {
        $user = $this->createUser();
        $this->assertEquals('PLN', Settings::scope($user)->get('currency', null));
    }

    public function test_get_with_explicit_default_overrides_definition(): void
    {
        $user = $this->createUser();
        // api_key has definition default=null, passing explicit default should return it
        $this->assertEquals('fallback', Settings::scope($user)->get('api_key', 'fallback'));
    }

    public function test_get_persisted_ignores_passed_default(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', 'real-key');
        $this->assertEquals('real-key', Settings::scope($user)->get('api_key', 'fallback'));
    }

    public function test_get_with_falsy_zero_default(): void
    {
        $user = $this->createUser();
        // api_key default is null, passing 0 should return 0
        $this->assertSame(0, Settings::scope($user)->get('api_key', 0));
    }

    public function test_get_with_falsy_empty_string_default(): void
    {
        $user = $this->createUser();
        $this->assertSame('', Settings::scope($user)->get('api_key', ''));
    }

    public function test_get_with_falsy_false_default(): void
    {
        $user = $this->createUser();
        $this->assertSame(false, Settings::scope($user)->get('api_key', false));
    }

    // ═══════════════════════════════════════════════════════════
    // allWithDefaults() — must work with all cast types
    // ═══════════════════════════════════════════════════════════

    public function test_all_with_defaults_does_not_crash(): void
    {
        $user = $this->createUser();
        // Must not throw on any cast type (BaseSetting, enum, array, etc.)
        $all = Settings::scope($user)->allWithDefaults();
        $this->assertCount(12, $all);
    }

    public function test_all_with_defaults_has_correct_names(): void
    {
        $user = $this->createUser();
        $all = Settings::scope($user)->allWithDefaults();
        $names = $all->pluck('name')->sort()->values()->toArray();
        $expected = [
            'allowed_countries', 'api_key', 'api_mode', 'api_mode_enum',
            'app_enabled', 'currency', 'maintenance_mode', 'max_weight',
            'orders_count', 'sender_info', 'shop_notes', 'webhook_url',
        ];
        $this->assertEquals($expected, $names);
    }

    public function test_all_with_defaults_persisted_values_override_defaults(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_mode', 'sandbox');
        Settings::scope($user)->set('currency', 'EUR');
        Settings::clearScope($user);

        $all = Settings::scope($user)->allWithDefaults();
        $this->assertEquals('sandbox', $all->where('name', 'api_mode')->first()->value);
        $this->assertEquals('EUR', $all->where('name', 'currency')->first()->value);
        // Non-persisted should still have defaults
        $this->assertTrue($all->where('name', 'app_enabled')->first()->value);
    }

    // ═══════════════════════════════════════════════════════════
    // setting() method — returns Setting model or null-id default
    // ═══════════════════════════════════════════════════════════

    public function test_setting_not_persisted_returns_null(): void
    {
        $user = $this->createUser();
        $setting = Settings::scope($user)->setting('api_key');
        // Not persisted → returns null (from cached all())
        $this->assertNull($setting);
    }

    public function test_setting_persisted_returns_model_with_id(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', 'test');
        $setting = Settings::scope($user)->setting('api_key');
        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertNotNull($setting->id);
    }

    // ═══════════════════════════════════════════════════════════
    // DB storage format verification
    // ═══════════════════════════════════════════════════════════

    public function test_morph_columns_correct(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', 'val');
        $raw = Setting::where('settingable_id', $user->id)->where('name', 'api_key')->first();
        $this->assertEquals($user->id, $raw->settingable_id);
        $this->assertEquals(User::class, $raw->settingable_type);
    }

    public function test_global_settings_have_null_morph(): void
    {
        $container = new SettingsContainer();
        $container->set('api_mode', 'sandbox');
        $raw = Setting::whereNull('settingable_id')->where('name', 'api_mode')->first();
        $this->assertNull($raw->settingable_id);
        $this->assertNull($raw->settingable_type);
    }

    public function test_unique_constraint_prevents_duplicates(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', 'first');
        Settings::scope($user)->set('api_key', 'second');
        $count = Setting::where('settingable_id', $user->id)->where('name', 'api_key')->count();
        $this->assertEquals(1, $count);
    }

    // ═══════════════════════════════════════════════════════════
    // Query count verification
    // ═══════════════════════════════════════════════════════════

    public function test_multiple_gets_single_query(): void
    {
        $user = $this->createUser();
        // First get triggers the all() query
        Settings::scope($user)->get('api_mode');

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) { $queryCount++; });

        // All subsequent gets must be zero queries (cached)
        Settings::scope($user)->get('api_mode');
        Settings::scope($user)->get('api_key');
        Settings::scope($user)->get('currency');
        Settings::scope($user)->get('app_enabled');
        Settings::scope($user)->get('max_weight');
        Settings::scope($user)->get('orders_count');
        Settings::scope($user)->get('maintenance_mode');
        Settings::scope($user)->get('shop_notes');
        Settings::scope($user)->get('webhook_url');

        $this->assertEquals(0, $queryCount, "Expected 0 queries from cache, got {$queryCount}");
    }
}
