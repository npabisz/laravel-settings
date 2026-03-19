<?php

namespace Npabisz\LaravelSettings\Tests;

use App\Models\Setting;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Npabisz\LaravelSettings\Facades\Settings;
use Npabisz\LaravelSettings\SettingsContainer;
use Npabisz\LaravelSettings\Tests\Fixtures\AdminUser;
use Npabisz\LaravelSettings\Tests\Fixtures\ApiSettingName;
use Npabisz\LaravelSettings\Tests\Fixtures\Shop;
use Npabisz\LaravelSettings\Tests\Fixtures\User;

class AdditionalEdgeCaseTest extends TestCase
{
    protected function createUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'test-shop.myshopify.com',
            'email' => 'test@example.com',
        ], $attrs));
    }

    // ═══════════════════════════════════════════════════════════
    // BackedEnum as setting NAME
    // ═══════════════════════════════════════════════════════════

    public function test_get_with_enum_name(): void
    {
        $user = $this->createUser();
        $value = Settings::scope($user)->get(ApiSettingName::ApiMode);
        $this->assertEquals('production', $value);
    }

    public function test_set_with_enum_name(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set(ApiSettingName::ApiMode, 'sandbox');
        $this->assertEquals('sandbox', Settings::scope($user)->get(ApiSettingName::ApiMode));
    }

    public function test_set_with_enum_name_persists(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set(ApiSettingName::ApiKey, 'my-key');
        Settings::clearScope($user);

        $this->assertEquals('my-key', Settings::scope($user)->get(ApiSettingName::ApiKey));
        $this->assertDatabaseHas('settings', [
            'settingable_id' => $user->id,
            'name' => 'api_key',
            'value' => 'my-key',
        ]);
    }

    public function test_enum_name_and_string_name_are_interchangeable(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set(ApiSettingName::Currency, 'EUR');
        $this->assertEquals('EUR', Settings::scope($user)->get('currency'));

        Settings::scope($user)->set('api_key', 'abc');
        $this->assertEquals('abc', Settings::scope($user)->get(ApiSettingName::ApiKey));
    }

    public function test_is_valid_setting_name_with_enum(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);
        $this->assertTrue($container->isValidSettingName(ApiSettingName::ApiMode));
        $this->assertTrue($container->isValidSettingName(ApiSettingName::AppEnabled));
    }

    public function test_setting_with_enum_name(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set(ApiSettingName::ApiKey, 'test');
        $setting = Settings::scope($user)->setting(ApiSettingName::ApiKey);
        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertEquals('test', $setting->value);
    }

    // ═══════════════════════════════════════════════════════════
    // Unsaved model (no ID)
    // ═══════════════════════════════════════════════════════════

    public function test_scope_with_unsaved_model(): void
    {
        $user = new User(['name' => 'unsaved', 'email' => 'unsaved@test.com']);
        // getKey() returns null for unsaved model

        $container = Settings::scope($user);
        $this->assertInstanceOf(SettingsContainer::class, $container);
    }

    public function test_scope_unsaved_model_can_read_defaults(): void
    {
        $user = new User(['name' => 'unsaved', 'email' => 'unsaved@test.com']);
        // Should be able to read defaults even without ID
        $value = Settings::scope($user)->get('api_mode');
        $this->assertEquals('production', $value);
    }

    // ═══════════════════════════════════════════════════════════
    // Multiple model types with HasSettings
    // ═══════════════════════════════════════════════════════════

    public function test_different_model_types_have_different_definitions(): void
    {
        $user = $this->createUser();
        $shop = Shop::create(['name' => 'shop', 'email' => 'shop@test.com']);

        // User has api_mode, Shop has shop_theme
        $this->assertEquals('production', Settings::scope($user)->get('api_mode'));
        $this->assertEquals('default', Settings::scope($shop)->get('shop_theme'));
    }

    public function test_different_model_types_are_isolated(): void
    {
        $user = $this->createUser();
        $shop = Shop::create(['name' => 'shop', 'email' => 'shop@test.com']);

        Settings::scope($shop)->set('shop_theme', 'dark');

        // User should not see Shop's settings
        $this->expectException(\Exception::class);
        Settings::scope($user)->get('shop_theme');
    }

    public function test_same_id_different_model_types_are_separate(): void
    {
        // Both could have id=1 but different settingable_type
        $user = $this->createUser();
        $shop = Shop::create(['name' => 'shop', 'email' => 'shop@test.com']);

        // Even if same ID, containers should be separate because class differs
        $userContainer = Settings::scope($user);
        $shopContainer = Settings::scope($shop);

        $this->assertNotSame($userContainer, $shopContainer);
    }

    // ═══════════════════════════════════════════════════════════
    // Inherited model (HasSettings from parent)
    // ═══════════════════════════════════════════════════════════

    public function test_inherited_model_is_accepted(): void
    {
        // AdminUser extends User, does not declare `use HasSettings`
        // class_uses_recursive should detect the trait from the parent
        $admin = AdminUser::create(['name' => 'admin', 'email' => 'admin@test.com']);

        $container = Settings::scope($admin);
        $this->assertInstanceOf(SettingsContainer::class, $container);
    }

    public function test_inherited_model_reads_parent_definitions(): void
    {
        $admin = AdminUser::create(['name' => 'admin', 'email' => 'admin@test.com']);

        $value = Settings::scope($admin)->get('api_mode');
        $this->assertEquals('production', $value);
    }

    public function test_inherited_model_set_and_get(): void
    {
        $admin = AdminUser::create(['name' => 'admin', 'email' => 'admin@test.com']);

        Settings::scope($admin)->set('api_key', 'admin-key');
        Settings::clearScope($admin);

        $this->assertEquals('admin-key', Settings::scope($admin)->get('api_key'));
    }

    public function test_parent_and_child_model_have_separate_scopes(): void
    {
        $user = $this->createUser();
        $admin = AdminUser::create(['name' => 'admin', 'email' => 'admin@test.com']);

        Settings::scope($user)->set('api_key', 'user-key');
        Settings::scope($admin)->set('api_key', 'admin-key');

        $this->assertEquals('user-key', Settings::scope($user)->get('api_key'));
        $this->assertEquals('admin-key', Settings::scope($admin)->get('api_key'));
    }

    // ═══════════════════════════════════════════════════════════
    // noCache() interaction with scope caching
    // ═══════════════════════════════════════════════════════════

    public function test_no_cache_on_scoped_container_queries_every_time(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', 'original');

        $container = Settings::scope($user)->noCache();

        $queryCount = 0;
        DB::listen(function ($q) use (&$queryCount) {
            if (str_contains($q->sql, 'settings')) $queryCount++;
        });

        // Each get should query individually
        $container->get('api_key');
        $container->get('api_mode');

        $this->assertGreaterThanOrEqual(2, $queryCount);
    }

    public function test_no_cache_affects_same_container_reference(): void
    {
        $user = $this->createUser();

        $container1 = Settings::scope($user);
        $container2 = $container1->noCache();

        // noCache returns $this, so same container
        $this->assertSame($container1, $container2);

        // Since scope() returns cached container, subsequent scope() calls
        // will return the same (now noCache'd) container
        $container3 = Settings::scope($user);
        $this->assertSame($container1, $container3);
    }

    public function test_cache_re_enables_after_no_cache(): void
    {
        $user = $this->createUser();

        $container = Settings::scope($user);
        $container->noCache();
        $container->cache();

        // First call loads cache
        $container->get('api_mode');

        $queryCount = 0;
        DB::listen(function ($q) use (&$queryCount) {
            if (str_contains($q->sql, 'settings')) $queryCount++;
        });

        $container->get('api_key');
        $this->assertEquals(0, $queryCount);
    }

    // ═══════════════════════════════════════════════════════════
    // set() → all() cache coherency
    // ═══════════════════════════════════════════════════════════

    public function test_set_then_all_shows_new_value(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        $container->set('api_key', 'new-key');

        $all = $container->all();
        $found = $all->where('name', 'api_key')->first();
        $this->assertNotNull($found);
        $this->assertEquals('new-key', $found->value);
    }

    public function test_set_updates_cached_all(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        // Load cache
        $container->all();

        // Set new value
        $container->set('api_key', 'updated');

        // all() should reflect the change without clearCache
        $all = $container->all();
        $found = $all->where('name', 'api_key')->first();
        $this->assertNotNull($found);
        $this->assertEquals('updated', $found->value);
    }

    public function test_set_then_get_without_clear(): void
    {
        $user = $this->createUser();
        $container = Settings::scope($user);

        // Load cache
        $container->get('api_mode');

        // Set new value
        $container->set('api_key', 'test-key');

        // Get should see new value immediately
        $this->assertEquals('test-key', $container->get('api_key'));
    }

    // ═══════════════════════════════════════════════════════════
    // settingsRelation() morph relationship
    // ═══════════════════════════════════════════════════════════

    public function test_settings_relation_returns_morph_many(): void
    {
        $user = $this->createUser();
        $relation = $user->settingsRelation();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $relation);
    }

    public function test_settings_relation_returns_persisted_settings(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', 'key1');
        Settings::scope($user)->set('api_mode', 'sandbox');

        $related = $user->settingsRelation()->get();
        $this->assertCount(2, $related);
        $this->assertContains('api_key', $related->pluck('name')->toArray());
        $this->assertContains('api_mode', $related->pluck('name')->toArray());
    }

    // ═══════════════════════════════════════════════════════════
    // Model serialization (queue job scenario)
    // ═══════════════════════════════════════════════════════════

    public function test_serialized_model_settings_still_work(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', 'before-serialize');

        // Simulate queue job serialization
        $serialized = serialize($user);
        $deserialized = unserialize($serialized);

        // After deserialization, settings should still work via facade
        $value = Settings::scope($deserialized)->get('api_key');
        $this->assertEquals('before-serialize', $value);
    }

    public function test_deserialized_model_gets_fresh_scope(): void
    {
        $user = $this->createUser();
        Settings::scope($user)->set('api_key', 'original');

        $serialized = serialize($user);

        // Change setting while "in transit"
        Setting::where('settingable_id', $user->id)
            ->where('name', 'api_key')
            ->update(['value' => 'changed']);

        // Simulate job boundary
        Settings::clearAllScopes();

        $deserialized = unserialize($serialized);
        $value = Settings::scope($deserialized)->get('api_key');
        $this->assertEquals('changed', $value);
    }

    // ═══════════════════════════════════════════════════════════
    // Queue: multiple jobs, same user
    // ═══════════════════════════════════════════════════════════

    public function test_queue_three_jobs_same_user_fresh_each_time(): void
    {
        $user = $this->createUser();

        // Job 1
        Settings::scope($user)->set('api_key', 'job1');
        $this->assertEquals('job1', Settings::scope($user)->get('api_key'));

        // Simulate job boundary
        event(new JobProcessing('redis', $this->createMockJob()));

        // External change
        Setting::where('settingable_id', $user->id)
            ->where('name', 'api_key')
            ->update(['value' => 'external']);

        // Job 2 — should see external change
        $this->assertEquals('external', Settings::scope($user)->get('api_key'));
        Settings::scope($user)->set('api_key', 'job2');

        // Simulate job boundary
        event(new JobProcessing('redis', $this->createMockJob()));

        // Job 3 — should see job2's change (persisted to DB)
        $this->assertEquals('job2', Settings::scope($user)->get('api_key'));
    }

    // ═══════════════════════════════════════════════════════════
    // scopeGlobal then scope — consistency
    // ═══════════════════════════════════════════════════════════

    public function test_scope_after_scope_global_returns_global_container(): void
    {
        $user = $this->createUser();

        $global = Settings::scopeGlobal($user);
        $scoped = Settings::scope($user);

        // scope() should return the container set by scopeGlobal()
        $this->assertSame($global, $scoped);
    }

    public function test_scope_global_overwrites_previous_scope(): void
    {
        $user = $this->createUser();

        $first = Settings::scope($user);
        $global = Settings::scopeGlobal($user);

        $this->assertNotSame($first, $global);
        $this->assertSame($global, Settings::scope($user));
    }

    // ═══════════════════════════════════════════════════════════
    // clearScope only clears specific model
    // ═══════════════════════════════════════════════════════════

    public function test_clear_scope_does_not_affect_other_users(): void
    {
        $user1 = $this->createUser(['email' => 'u1@test.com', 'name' => 's1']);
        $user2 = $this->createUser(['email' => 'u2@test.com', 'name' => 's2']);

        $c1 = Settings::scope($user1);
        $c2 = Settings::scope($user2);

        Settings::clearScope($user1);

        // user1 gets new container, user2 keeps old one
        $this->assertNotSame($c1, Settings::scope($user1));
        $this->assertSame($c2, Settings::scope($user2));
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    protected function createMockJob()
    {
        return new class {
            public function resolveName() { return 'TestJob'; }
            public function getConnectionName() { return 'redis'; }
            public function payload() { return []; }
        };
    }
}
