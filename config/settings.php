<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Setting Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model class used to persist settings. Override this when
    | your project keeps the Setting model outside of App\Models — for
    | example, inside a DDD domain folder such as
    | App\Domain\Settings\Models\Setting.
    |
    | The class must extend Npabisz\LaravelSettings\Models\AbstractSetting.
    |
    */
    'model' => \App\Models\Setting::class,
];
