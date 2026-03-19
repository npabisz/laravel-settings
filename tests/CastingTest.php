<?php

namespace Npabisz\LaravelSettings\Tests;

use App\Models\Setting;
use Npabisz\LaravelSettings\Facades\Settings;
use Npabisz\LaravelSettings\Tests\Fixtures\User;
use Npabisz\LaravelSettings\Tests\Fixtures\UserWithEnumSettings;

class CastingTest extends TestCase
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

    // ─── Primitive casts ────────────────────────────────────

    public function test_bool_cast_true(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('app_enabled', true);
        Settings::clearScope($user);

        $this->assertTrue(Settings::scope($user)->get('app_enabled'));
    }

    public function test_bool_cast_false(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('app_enabled', false);
        Settings::clearScope($user);

        $this->assertFalse(Settings::scope($user)->get('app_enabled'));
    }

    public function test_float_cast(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('max_weight', 30.5);
        Settings::clearScope($user);

        $value = Settings::scope($user)->get('max_weight');
        $this->assertIsFloat($value);
        $this->assertEquals(30.5, $value);
    }

    public function test_string_value(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_key', 'my-secret-key');
        Settings::clearScope($user);

        $this->assertEquals('my-secret-key', Settings::scope($user)->get('api_key'));
    }

    public function test_null_value_for_nullable_setting(): void
    {
        $user = $this->createUser();

        // api_key defaults to null
        $this->assertNull(Settings::scope($user)->get('api_key'));
    }

    // ─── Default values ─────────────────────────────────────

    public function test_default_bool(): void
    {
        $user = $this->createUser();
        $this->assertTrue(Settings::scope($user)->get('app_enabled'));
    }

    public function test_default_string_with_options(): void
    {
        $user = $this->createUser();
        $this->assertEquals('production', Settings::scope($user)->get('api_mode'));
    }

    public function test_default_null(): void
    {
        $user = $this->createUser();
        $this->assertNull(Settings::scope($user)->get('api_key'));
    }

    public function test_default_float(): void
    {
        $user = $this->createUser();
        $this->assertEquals(25.0, Settings::scope($user)->get('max_weight'));
    }

    public function test_default_string(): void
    {
        $user = $this->createUser();
        $this->assertEquals('PLN', Settings::scope($user)->get('currency'));
    }

    // ─── Persistence across container instances ─────────────

    public function test_value_persists_after_clear_scope(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_key', 'persistent-key');
        Settings::clearScope($user);

        $this->assertEquals('persistent-key', Settings::scope($user)->get('api_key'));
    }

    public function test_multiple_settings_persist(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('api_mode', 'sandbox');
        Settings::scope($user)->set('api_key', 'key-123');
        Settings::scope($user)->set('currency', 'PLN');
        Settings::clearScope($user);

        $this->assertEquals('sandbox', Settings::scope($user)->get('api_mode'));
        $this->assertEquals('key-123', Settings::scope($user)->get('api_key'));
        $this->assertEquals('PLN', Settings::scope($user)->get('currency'));
    }

    // ─── Raw DB value vs cast value ─────────────────────────

    public function test_bool_stored_as_string_in_db(): void
    {
        $user = $this->createUser();

        Settings::scope($user)->set('app_enabled', false);

        $raw = Setting::where('settingable_id', $user->id)
            ->where('name', 'app_enabled')
            ->first();

        // In DB it's stored as "0" or "" string
        $this->assertNotNull($raw);

        // But when accessed via Settings it's cast to bool
        Settings::clearScope($user);
        $this->assertFalse(Settings::scope($user)->get('app_enabled'));
    }
}
