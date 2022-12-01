<?php

namespace Npabisz\LaravelSettings;

use Npabisz\LaravelSettings\Facades\Accessors\SettingsAccessor;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register Settings Facade
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('settings', function ($app) {
            return new SettingsAccessor(Auth::user());
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->offerPublishing();
    }

    /**
     * Setup the resource publishing groups for settings
     *
     * @return void
     */
    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'settings-migrations');

            $this->publishes([
                __DIR__.'/../stubs/Setting.stub' => app_path('Models/Setting.php'),
            ], 'settings-model');
        }
    }
}