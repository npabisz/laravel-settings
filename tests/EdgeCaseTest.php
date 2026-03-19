<?php

namespace Npabisz\LaravelSettings\Tests;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Npabisz\LaravelSettings\Facades\Settings;
use Npabisz\LaravelSettings\SettingsContainer;
use Npabisz\LaravelSettings\Tests\Fixtures\User;

class EdgeCaseTest extends TestCase
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

    // ─── Rapid set/get cycles ───────────────────────────────

    public function test_rapid_set_get_same_key(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $container->set('api_mode', 'sandbox');
        $this->assertEquals('sandbox', $container->get('api_mode'));

        $container->set('api_mode', 'production');
        $this->assertEquals('production', $container->get('api_mode'));

        $container->set('api_mode', 'sandbox');
        $this->assertEquals('sandbox', $container->get('api_mode'));

        // Only 1 row in DB (upsert)
        $this->assertEquals(1, Setting::where('settingable_id', $user->id)->where('name', 'api_mode')->count());
    }

    public function test_set_all_settings_then_get_all(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $container->set('app_enabled', false);
        $container->set('api_mode', 'sandbox');
        $container->set('api_key', 'key-123');
        $container->set('currency', 'PLN');
        $container->set('max_weight', 10.5);

        // All should be readable from same container
        $this->assertFalse($container->get('app_enabled'));
        $this->assertEquals('sandbox', $container->get('api_mode'));
        $this->assertEquals('key-123', $container->get('api_key'));
        $this->assertEquals('PLN', $container->get('currency'));
        $this->assertEquals(10.5, $container->get('max_weight'));

        // And from fresh container
        Settings::clearScope($user);
        $fresh = Settings::scope($user);
        $this->assertFalse($fresh->get('app_enabled'));
        $this->assertEquals('sandbox', $fresh->get('api_mode'));
        $this->assertEquals('key-123', $fresh->get('api_key'));
    }

    // ─── allWithDefaults() ──────────────────────────────────

    public function test_all_with_defaults_merges_persisted_and_defaults(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $container->set('api_mode', 'sandbox');
        $container->set('api_key', 'key-123');

        Settings::clearScope($user);
        $all = Settings::scope($user)->allWithDefaults();

        $this->assertCount(12, $all);

        // Persisted values
        $this->assertEquals('sandbox', $all->where('name', 'api_mode')->first()->value);
        $this->assertEquals('key-123', $all->where('name', 'api_key')->first()->value);

        // Default values
        $this->assertTrue($all->where('name', 'app_enabled')->first()->value);
        $this->assertEquals('PLN', $all->where('name', 'currency')->first()->value);
    }

    public function test_all_with_defaults_caches_result(): void
    {
        $user = $this->createUser();

        $result1 = Settings::scope($user)->allWithDefaults();
        $result2 = Settings::scope($user)->allWithDefaults();

        $this->assertSame($result1, $result2);
    }

    // ─── Concurrent modifications ───────────────────────────

    public function test_external_db_change_not_visible_until_clear(): void
    {
        $user = $this->createUser();

        // Load into cache
        Settings::scope($user)->set('api_mode', 'sandbox');

        // External change
        Setting::where('settingable_id', $user->id)
            ->where('name', 'api_mode')
            ->update(['value' => 'production']);

        // Cache still returns old value
        $this->assertEquals('sandbox', Settings::scope($user)->get('api_mode'));

        // After clear, returns new value
        Settings::clearScope($user);
        $this->assertEquals('production', Settings::scope($user)->get('api_mode'));
    }

    // ─── Empty string values ────────────────────────────────

    public function test_empty_string_value(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_key', '');
        Settings::clearScope($user);

        $this->assertEquals('', Settings::scope($user)->get('api_key'));
    }

    // ─── Long values ────────────────────────────────────────

    public function test_long_string_value(): void
    {
        $user = $this->createUser();

        $longValue = str_repeat('a', 5000);
        Settings::scope($user)->set('api_key', $longValue);
        Settings::clearScope($user);

        $this->assertEquals($longValue, Settings::scope($user)->get('api_key'));
    }

    // ─── Falsy default values ─────────────────────────────

    public function test_get_with_falsy_default_zero(): void
    {
        $user = $this->createUser();

        // api_key has default null, pass 0 as explicit default
        $value = Settings::scope($user)->get('api_key', 0);
        $this->assertSame(0, $value);
    }

    public function test_get_with_falsy_default_empty_string(): void
    {
        $user = $this->createUser();

        $value = Settings::scope($user)->get('api_key', '');
        $this->assertSame('', $value);
    }

    public function test_get_with_falsy_default_false(): void
    {
        $user = $this->createUser();

        $value = Settings::scope($user)->get('api_key', false);
        $this->assertSame(false, $value);
    }

    // ─── Multiple users stress ──────────────────────────────

    public function test_many_users_isolated(): void
    {
        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $users[] = $this->createUser([
                'email' => "user{$i}@example.com",
                'name' => "shop{$i}.myshopify.com",
            ]);
        }

        // Set different values per user
        foreach ($users as $i => $user) {
            Settings::scope($user)->set('api_key', "key-{$i}");
            Settings::scope($user)->set('api_mode', $i % 2 === 0 ? 'production' : 'sandbox');
        }

        // Verify isolation
        foreach ($users as $i => $user) {
            $this->assertEquals("key-{$i}", Settings::scope($user)->get('api_key'));
            $this->assertEquals(
                $i % 2 === 0 ? 'production' : 'sandbox',
                Settings::scope($user)->get('api_mode')
            );
        }
    }

    // ─── Facade __call magic method ─────────────────────────

    public function test_facade_forwards_global_calls(): void
    {
        // Settings::get() should forward to global (unscoped) container
        $value = Settings::get('api_mode');
        $this->assertEquals('production', $value);
    }

    public function test_facade_user_shorthand(): void
    {
        $user = $this->createUser();
        Settings::scopeGlobal($user);

        // Settings::user() should return the scoped container via __call magic
        $container = Settings::user();
        $this->assertInstanceOf(SettingsContainer::class, $container);
    }

    // ─── setting() method ───────────────────────────────────

    public function test_setting_returns_null_for_unpersisted(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $setting = $container->setting('api_key');
        // Returns a Setting model but with id=null (default)
        $this->assertNull($setting?->id ?? null);
    }

    public function test_setting_returns_model_for_persisted(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $container->set('api_key', 'my-key');
        $setting = $container->setting('api_key');

        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertNotNull($setting->id);
        $this->assertEquals('my-key', $setting->value);
    }

    // ─── Database relationship ──────────────────────────────

    public function test_settings_use_morph_relation(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_key', 'test');

        $setting = Setting::where('settingable_id', $user->id)->first();
        $this->assertEquals(User::class, $setting->settingable_type);
        $this->assertEquals($user->id, $setting->settingable_id);
    }

    public function test_unique_constraint_per_model_and_name(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_key', 'first');
        Settings::scope($user)->set('api_key', 'second');

        // Should have exactly 1 row (updated, not duplicated)
        $count = Setting::where('settingable_id', $user->id)
            ->where('name', 'api_key')
            ->count();

        $this->assertEquals(1, $count);
    }

    // ─── clearScope on non-existent scope ───────────────────

    public function test_clear_scope_on_never_scoped_user_no_error(): void
    {
        $user = $this->createUser();

        // Should not throw
        Settings::clearScope($user);

        // And scope should still work after
        $this->assertEquals('production', Settings::scope($user)->get('api_mode'));
    }

    public function test_clear_all_scopes_when_empty_no_error(): void
    {
        // Should not throw
        Settings::clearAllScopes();

        $user = $this->createUser();
        $this->assertEquals('production', Settings::scope($user)->get('api_mode'));
    }
}
