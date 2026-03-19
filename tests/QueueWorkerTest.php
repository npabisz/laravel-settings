<?php

namespace Npabisz\LaravelSettings\Tests;

use App\Models\Setting;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Npabisz\LaravelSettings\Facades\Settings;
use Npabisz\LaravelSettings\Tests\Fixtures\User;

class QueueWorkerTest extends TestCase
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

    // ─── Queue worker cache reset ───────────────────────────

    public function test_job_processing_event_clears_all_scopes(): void
    {
        $user = $this->createUser();

        // Load settings into cache
        $container1 = Settings::scope($user);
        $container1->get('api_mode');

        // Simulate JobProcessing event (fired before each job in queue worker)
        event(new JobProcessing('redis', $this->createMockJob()));

        // After event, scope should return new container
        $container2 = Settings::scope($user);
        $this->assertNotSame($container1, $container2);
    }

    public function test_stale_data_cleared_between_jobs(): void
    {
        $user = $this->createUser();

        // Job 1: set and cache setting
        Settings::scope($user)->set('api_mode', 'sandbox');
        $this->assertEquals('sandbox', Settings::scope($user)->get('api_mode'));

        // Simulate external change (another process changed the setting)
        Setting::where('settingable_id', $user->id)
            ->where('name', 'api_mode')
            ->update(['value' => 'production']);

        // Without clearing — stale data
        $this->assertEquals('sandbox', Settings::scope($user)->get('api_mode'));

        // Simulate next job starting
        event(new JobProcessing('redis', $this->createMockJob()));

        // After clearing — fresh data from DB
        $this->assertEquals('production', Settings::scope($user)->get('api_mode'));
    }

    public function test_multiple_users_cleared_between_jobs(): void
    {
        $user1 = $this->createUser(['email' => 'user1@example.com', 'name' => 'shop1.myshopify.com']);
        $user2 = $this->createUser(['email' => 'user2@example.com', 'name' => 'shop2.myshopify.com']);

        // Load both into cache
        $c1 = Settings::scope($user1);
        $c2 = Settings::scope($user2);
        $c1->get('api_mode');
        $c2->get('api_mode');

        // Simulate job processing
        event(new JobProcessing('redis', $this->createMockJob()));

        // Both should be fresh containers
        $this->assertNotSame($c1, Settings::scope($user1));
        $this->assertNotSame($c2, Settings::scope($user2));
    }

    public function test_fresh_queries_after_job_processing_event(): void
    {
        $user = $this->createUser();

        // Initial load
        Settings::scope($user)->get('api_mode');

        // Simulate job boundary
        event(new JobProcessing('redis', $this->createMockJob()));

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // Should query DB again (cache was cleared)
        Settings::scope($user)->get('api_mode');

        $this->assertEquals(1, $queryCount, 'Expected 1 fresh DB query after job boundary');
    }

    protected function createMockJob()
    {
        return new class {
            public function resolveName() { return 'TestJob'; }
            public function getConnectionName() { return 'redis'; }
            public function payload() { return []; }
        };
    }
}
