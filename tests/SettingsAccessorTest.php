<?php

namespace Npabisz\LaravelSettings\Tests;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Npabisz\LaravelSettings\Facades\Settings;
use Npabisz\LaravelSettings\Tests\Fixtures\User;

class SettingsAccessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the Setting fixture is loaded
        //
    }

    protected function createUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'test-shop.myshopify.com',
            'email' => 'test@example.com',
        ], $attrs));
    }

    // ─── scope() caching ────────────────────────────────────

    public function test_scope_returns_same_container_for_same_model(): void
    {
        $user = $this->createUser();

        $container1 = Settings::scope($user);
        $container2 = Settings::scope($user);

        $this->assertSame($container1, $container2);
    }

    public function test_scope_returns_same_container_for_different_instances_same_id(): void
    {
        $user = $this->createUser();

        $user1 = User::find($user->id);
        $user2 = User::find($user->id);

        // Different PHP objects, same DB record
        $this->assertNotSame($user1, $user2);

        $container1 = Settings::scope($user1);
        $container2 = Settings::scope($user2);

        $this->assertSame($container1, $container2);
    }

    public function test_scope_returns_different_containers_for_different_users(): void
    {
        $user1 = $this->createUser(['email' => 'user1@example.com', 'name' => 'shop1.myshopify.com']);
        $user2 = $this->createUser(['email' => 'user2@example.com', 'name' => 'shop2.myshopify.com']);

        $container1 = Settings::scope($user1);
        $container2 = Settings::scope($user2);

        $this->assertNotSame($container1, $container2);
    }

    public function test_scope_caching_reduces_db_queries(): void
    {
        $user = $this->createUser();

        // First call — should query DB
        Settings::scope($user)->get('api_mode');

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // Subsequent calls — should NOT query DB (cached container + cached settings)
        Settings::scope($user)->get('api_mode');
        Settings::scope($user)->get('api_key');
        Settings::scope($user)->get('currency');
        Settings::scope($user)->get('app_enabled');

        $this->assertEquals(0, $queryCount, 'Expected zero DB queries after initial load, got ' . $queryCount);
    }

    public function test_multiple_get_calls_only_one_db_query(): void
    {
        $user = $this->createUser();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // All these should result in exactly 1 DB query (the initial all() load)
        $container = Settings::scope($user);
        $container->get('api_mode');
        $container->get('api_key');
        $container->get('currency');
        $container->get('app_enabled');
        $container->get('max_weight');

        $this->assertEquals(1, $queryCount, 'Expected exactly 1 DB query for all settings, got ' . $queryCount);
    }

    // ─── scopeGlobal() ─────────────────────────────────────

    public function test_scope_global_overwrites_cached_scope(): void
    {
        $user = $this->createUser();

        $container1 = Settings::scope($user);
        $container2 = Settings::scopeGlobal($user);

        $this->assertNotSame($container1, $container2);

        // After scopeGlobal, scope() should return the new container
        $container3 = Settings::scope($user);
        $this->assertSame($container2, $container3);
    }

    // ─── clearScope() ───────────────────────────────────────

    public function test_clear_scope_removes_cached_container(): void
    {
        $user = $this->createUser();

        $container1 = Settings::scope($user);
        Settings::clearScope($user);
        $container2 = Settings::scope($user);

        $this->assertNotSame($container1, $container2);
    }

    public function test_clear_scope_forces_fresh_db_query(): void
    {
        $user = $this->createUser();

        // Load settings
        Settings::scope($user)->get('api_mode');

        // Change setting directly in DB (simulating another process)
        Setting::where('settingable_id', $user->id)
            ->where('name', 'api_mode')
            ->delete();

        Setting::create([
            'settingable_id' => $user->id,
            'settingable_type' => User::class,
            'name' => 'api_mode',
            'value' => 'sandbox',
        ]);

        // Without clearing — still returns old cached value
        $this->assertEquals('production', Settings::scope($user)->get('api_mode'));

        // After clearing — fetches fresh from DB
        Settings::clearScope($user);
        $this->assertEquals('sandbox', Settings::scope($user)->get('api_mode'));
    }

    // ─── clearAllScopes() ───────────────────────────────────

    public function test_clear_all_scopes_removes_all_cached_containers(): void
    {
        $user1 = $this->createUser(['email' => 'user1@example.com', 'name' => 'shop1.myshopify.com']);
        $user2 = $this->createUser(['email' => 'user2@example.com', 'name' => 'shop2.myshopify.com']);

        $container1a = Settings::scope($user1);
        $container2a = Settings::scope($user2);

        Settings::clearAllScopes();

        $container1b = Settings::scope($user1);
        $container2b = Settings::scope($user2);

        $this->assertNotSame($container1a, $container1b);
        $this->assertNotSame($container2a, $container2b);
    }

    // ─── HasSettings trait ($user->settings) ────────────────

    public function test_user_settings_attribute_uses_cached_scope(): void
    {
        $user = $this->createUser();

        // First access
        $user->settings->get('api_mode');

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // Subsequent accesses via $user->settings — should be cached
        $user->settings->get('api_mode');
        $user->settings->get('currency');
        $user->settings->get('app_enabled');

        $this->assertEquals(0, $queryCount);
    }

    public function test_user_settings_attribute_same_container_as_facade_scope(): void
    {
        $user = $this->createUser();

        $fromFacade = Settings::scope($user);
        $fromAttribute = $user->settings;

        $this->assertSame($fromFacade, $fromAttribute);
    }

    // ─── get/set functionality ──────────────────────────────

    public function test_get_returns_default_when_not_set(): void
    {
        $user = $this->createUser();

        $this->assertEquals('production', Settings::scope($user)->get('api_mode'));
        $this->assertNull(Settings::scope($user)->get('api_key'));
        $this->assertTrue(Settings::scope($user)->get('app_enabled'));
        $this->assertEquals('PLN', Settings::scope($user)->get('currency'));
    }

    public function test_set_and_get(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_mode', 'sandbox');
        $this->assertEquals('sandbox', Settings::scope($user)->get('api_mode'));
    }

    public function test_set_persists_to_database(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_key', 'test-key-123');

        $this->assertDatabaseHas('settings', [
            'settingable_id' => $user->id,
            'settingable_type' => User::class,
            'name' => 'api_key',
            'value' => 'test-key-123',
        ]);
    }

    public function test_set_updates_cached_container(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->get('api_mode'); // load cache
        Settings::scope($user)->set('api_mode', 'sandbox');

        // Should return new value without clearing cache
        $this->assertEquals('sandbox', Settings::scope($user)->get('api_mode'));
    }

    public function test_set_invalid_value_throws_exception(): void
    {
        $user = $this->createUser();

        $this->expectException(\Exception::class);
        Settings::scope($user)->set('api_mode', 'invalid_value');
    }

    public function test_get_invalid_name_throws_exception(): void
    {
        $user = $this->createUser();

        $this->expectException(\Exception::class);
        Settings::scope($user)->get('nonexistent_setting');
    }

    // ─── all() and allWithDefaults() ────────────────────────

    public function test_all_returns_only_persisted_settings(): void
    {
        $user = $this->createUser();

        $all = Settings::scope($user)->all();
        $this->assertCount(0, $all);

        Settings::scope($user)->set('api_mode', 'sandbox');

        Settings::clearScope($user);
        $all = Settings::scope($user)->all();
        $this->assertCount(1, $all);
    }

    public function test_all_with_defaults_includes_non_persisted(): void
    {
        $user = $this->createUser();

        $all = Settings::scope($user)->allWithDefaults();
        $this->assertCount(12, $all); // All defined settings
    }

    // ─── noCache() mode ─────────────────────────────────────

    public function test_no_cache_mode_queries_db_every_time(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_mode', 'sandbox');

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            if (str_contains($query->sql, 'settings')) {
                $queryCount++;
            }
        });

        Settings::clearScope($user);
        $container = Settings::scope($user)->noCache();
        $container->get('api_mode');
        $container->get('api_mode');

        // noCache should query each time (individual queries per get)
        $this->assertGreaterThan(1, $queryCount);
    }

    // ─── isScopedTo() ───────────────────────────────────────

    public function test_is_scoped_to_same_model(): void
    {
        $user = $this->createUser();

        $container = Settings::scope($user);

        $this->assertTrue($container->isScopedTo($user));
    }

    public function test_is_scoped_to_different_instance_same_id(): void
    {
        $user = $this->createUser();

        $container = Settings::scope($user);
        $userCopy = User::find($user->id);

        $this->assertTrue($container->isScopedTo($userCopy));
    }

    public function test_is_not_scoped_to_different_user(): void
    {
        $user1 = $this->createUser(['email' => 'user1@example.com', 'name' => 'shop1.myshopify.com']);
        $user2 = $this->createUser(['email' => 'user2@example.com', 'name' => 'shop2.myshopify.com']);

        $container = Settings::scope($user1);

        $this->assertFalse($container->isScopedTo($user2));
    }

    public function test_is_scoped_to_null_returns_false(): void
    {
        $user = $this->createUser();

        $container = Settings::scope($user);

        $this->assertFalse($container->isScopedTo(null));
    }

    // ─── Multi-tenant isolation ─────────────────────────────

    public function test_different_users_have_isolated_settings(): void
    {
        $user1 = $this->createUser(['email' => 'user1@example.com', 'name' => 'shop1.myshopify.com']);
        $user2 = $this->createUser(['email' => 'user2@example.com', 'name' => 'shop2.myshopify.com']);

        Settings::scope($user1)->set('api_mode', 'sandbox');
        Settings::scope($user2)->set('api_mode', 'production');

        $this->assertEquals('sandbox', Settings::scope($user1)->get('api_mode'));
        $this->assertEquals('production', Settings::scope($user2)->get('api_mode'));
    }

    public function test_setting_one_user_does_not_affect_another_users_cache(): void
    {
        $user1 = $this->createUser(['email' => 'user1@example.com', 'name' => 'shop1.myshopify.com']);
        $user2 = $this->createUser(['email' => 'user2@example.com', 'name' => 'shop2.myshopify.com']);

        // Load both users' settings into cache
        Settings::scope($user1)->get('api_mode');
        Settings::scope($user2)->get('api_mode');

        // Change user1's setting
        Settings::scope($user1)->set('api_mode', 'sandbox');

        // user2 should still have default
        $this->assertEquals('production', Settings::scope($user2)->get('api_mode'));
        $this->assertEquals('sandbox', Settings::scope($user1)->get('api_mode'));
    }
}
