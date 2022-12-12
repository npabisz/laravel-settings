<?php

namespace Npabisz\LaravelSettings\Traits;

use Npabisz\LaravelSettings\Facades\Settings;
use Npabisz\LaravelSettings\SettingsContainer;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property-read SettingsContainer $settings
 */
trait HasSettings
{
    use HasSettingsDefinitions;

    /**
     * @var ?SettingsContainer
     */
    protected ?SettingsContainer $settingsContainer = null;

    /**
     * @return MorphMany
     */
    public function settingsRelation (): MorphMany
    {
        return $this->morphMany(Setting::class, 'settingable');
    }

    /**
     * @return SettingsContainer
     */
    public function getSettingsAttribute (): SettingsContainer
    {
        return Settings::scope($this);
    }
}
