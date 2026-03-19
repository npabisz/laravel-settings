<?php

namespace Npabisz\LaravelSettings\Tests;

use App\Models\Setting;
use Npabisz\LaravelSettings\SettingsContainer;
use Npabisz\LaravelSettings\Tests\Fixtures\User;

class SettingsContainerTest extends TestCase
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

    // ─── Basic CRUD ─────────────────────────────────────────

    public function test_get_default_value(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $this->assertEquals('production', $container->get('api_mode'));
    }

    public function test_set_creates_setting_in_db(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $container->set('api_mode', 'sandbox');

        $this->assertDatabaseHas('settings', [
            'settingable_id' => $user->id,
            'name' => 'api_mode',
            'value' => 'sandbox',
        ]);
    }

    public function test_set_updates_existing_setting(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $container->set('api_mode', 'sandbox');
        $container->set('api_mode', 'production');

        $this->assertEquals('production', $container->get('api_mode'));
        $this->assertEquals(1, Setting::where('settingable_id', $user->id)->where('name', 'api_mode')->count());
    }

    public function test_get_after_set_returns_new_value(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $container->set('api_key', 'my-key');
        $this->assertEquals('my-key', $container->get('api_key'));
    }

    // ─── In-memory caching ──────────────────────────────────

    public function test_all_caches_results_in_memory(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $all1 = $container->all();
        $all2 = $container->all();

        $this->assertSame($all1, $all2);
    }

    public function test_clear_cache_forces_reload(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $container->all(); // loads cache

        // Modify DB directly
        Setting::create([
            'settingable_id' => $user->id,
            'settingable_type' => User::class,
            'name' => 'api_key',
            'value' => 'sneaky-key',
        ]);

        // Still cached
        $this->assertNull($container->get('api_key'));

        // Clear and reload
        $container->clearCache();
        $this->assertEquals('sneaky-key', $container->get('api_key'));
    }

    // ─── Validation ─────────────────────────────────────────

    public function test_valid_setting_name(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $this->assertTrue($container->isValidSettingName('api_mode'));
        $this->assertFalse($container->isValidSettingName('nonexistent'));
    }

    public function test_valid_setting_value_with_options(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $this->assertTrue($container->isValidSettingValue('api_mode', 'production'));
        $this->assertTrue($container->isValidSettingValue('api_mode', 'sandbox'));
        $this->assertFalse($container->isValidSettingValue('api_mode', 'invalid'));
    }

    public function test_valid_setting_value_without_options(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        // api_key has no options — any value is valid
        $this->assertTrue($container->isValidSettingValue('api_key', 'anything'));
        $this->assertTrue($container->isValidSettingValue('api_key', ''));
    }

    // ─── Casting ────────────────────────────────────────────

    public function test_bool_cast(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $this->assertTrue($container->get('app_enabled'));

        $container->set('app_enabled', false);
        $this->assertFalse($container->get('app_enabled'));
    }

    public function test_float_cast_default(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $value = $container->get('max_weight');
        $this->assertEquals(25.0, $value);
    }

    // ─── isScopedTo() ───────────────────────────────────────

    public function test_is_scoped_to_with_same_id_different_object(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $userCopy = User::find($user->id);
        $this->assertNotSame($user, $userCopy);
        $this->assertTrue($container->isScopedTo($userCopy));
    }

    public function test_is_scoped_to_with_different_id(): void
    {
        $user1 = $this->createUser(['email' => 'a@test.com', 'name' => 'shop-a']);
        $user2 = $this->createUser(['email' => 'b@test.com', 'name' => 'shop-b']);

        $container = new SettingsContainer($user1);
        $this->assertFalse($container->isScopedTo($user2));
    }

    // ─── Global (unscoped) settings ─────────────────────────

    public function test_unscoped_container_reads_global_settings(): void
    {
        // Global definitions come from Setting::getGlobalSettingsDefinitions()
        // which in our fixture returns the same definitions as user settings
        $container = new SettingsContainer();

        $this->assertEquals('production', $container->get('api_mode'));
        $this->assertTrue($container->get('app_enabled'));
    }

    public function test_unscoped_container_set_and_get(): void
    {
        $container = new SettingsContainer();

        $container->set('api_mode', 'sandbox');
        $this->assertEquals('sandbox', $container->get('api_mode'));

        $this->assertDatabaseHas('settings', [
            'settingable_id' => null,
            'settingable_type' => null,
            'name' => 'api_mode',
            'value' => 'sandbox',
        ]);
    }

    public function test_global_and_scoped_settings_are_separate(): void
    {
        $user = $this->createUser();

        $global = new SettingsContainer();
        $scoped = new SettingsContainer($user);

        $global->set('api_mode', 'sandbox');
        $scoped->set('api_mode', 'production');

        // Each has its own value
        $global->clearCache();
        $scoped->clearCache();
        $this->assertEquals('sandbox', $global->get('api_mode'));
        $this->assertEquals('production', $scoped->get('api_mode'));

        // DB has 2 separate rows
        $this->assertEquals(2, \App\Models\Setting::where('name', 'api_mode')->count());
    }

    // ─── noCache / cache toggle ─────────────────────────────

    public function test_no_cache_returns_self(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $result = $container->noCache();
        $this->assertSame($container, $result);
    }

    public function test_cache_returns_self(): void
    {
        $user = $this->createUser();
        $container = new SettingsContainer($user);

        $result = $container->cache();
        $this->assertSame($container, $result);
    }
}
