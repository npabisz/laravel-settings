<?php

namespace Npabisz\LaravelSettings;

use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
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
                __DIR__.'/../stubs/SettingsServiceProvider.stub' => app_path('Providers/SettingsServiceProvider.php'),
            ], 'settings-provider');

            $this->publishes([
                __DIR__.'/../stubs/Setting.stub' => app_path('Models/Setting.php'),
            ], 'settings-model');
        }
    }
}