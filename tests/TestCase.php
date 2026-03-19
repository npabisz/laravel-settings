<?php

namespace Npabisz\LaravelSettings\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Npabisz\LaravelSettings\SettingsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SettingsServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Settings' => \Npabisz\LaravelSettings\Facades\Settings::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('settingable_id')->nullable();
            $table->string('settingable_type')->nullable();
            $table->string('name');
            $table->text('value')->nullable();
            $table->unique(['settingable_id', 'settingable_type', 'name']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }
}
