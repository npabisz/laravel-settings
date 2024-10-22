<?php

namespace Npabisz\LaravelSettings\Facades;

use Npabisz\LaravelSettings\SettingsContainer;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static SettingsContainer user ()
 * @method static SettingsContainer scope (Model $model)
 * @method static SettingsContainer scopeGlobal (Model $model)
 * @method static bool isValidSettingName (\BackedEnum|string $name): bool
 * @method static bool isValidSettingValue (\BackedEnum|string $name, mixed $value)
 * @method static bool isScopedTo (?Model $model = null)
 * @method static array getSettingOptions (\BackedEnum|string $name)
 * @method static Setting|null setting (\BackedEnum|string $name)
 * @method static mixed get (\BackedEnum|string $name, mixed $default = null)
 * @method static Setting set (\BackedEnum|string $name, mixed $value)
 * @method static Collection all ()
 * @method static Collection allWithDefaults ()
 * @method static clearCache()
 * @method static SettingsContainer noCache ()
 * @method static SettingsContainer cache ()
 *
 */
class Settings extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'settings';
    }
}
