<?php

namespace Npabisz\LaravelSettings\Facades\Accessors;

use Npabisz\LaravelSettings\SettingsContainer;
use BadMethodCallException;
use Error;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\ForwardsCalls;

class SettingsAccessor
{
    use ForwardsCalls;

    /**
     * @var SettingsContainer
     */
    protected SettingsContainer $settings;

    /**
     * Scoped settings containers keyed by "ClassName:id"
     *
     * @var SettingsContainer[]
     */
    protected array $scopedSettings = [];

    /**
     * @param ?Model $model
     */
    public function __construct (?Model $model = null)
    {
        $this->settings = new SettingsContainer();

        if ($model !== null) {
            $this->scopeGlobal($model);
        }
    }

    /**
     * Get or create a cached SettingsContainer for the given model.
     *
     * The container is cached per model class and primary key,
     * so subsequent calls with the same model return the same
     * container (with its in-memory settings cache intact).
     *
     * @param Model $model
     *
     * @throws \Exception
     *
     * @return SettingsContainer
     */
    public function scope (Model $model): SettingsContainer
    {
        $key = $this->getScopeKey($model);

        if (isset($this->scopedSettings[$key])) {
            return $this->scopedSettings[$key];
        }

        return $this->scopedSettings[$key] = new SettingsContainer($model);
    }

    /**
     * @param Model $model
     *
     * @throws \Exception
     *
     * @return SettingsContainer
     */
    public function scopeGlobal (Model $model): SettingsContainer
    {
        $key = $this->getScopeKey($model);

        return $this->scopedSettings[$key] = new SettingsContainer($model, true);
    }

    /**
     * Clear the cached scope for a specific model.
     *
     * @param Model $model
     *
     * @return void
     */
    public function clearScope (Model $model): void
    {
        $key = $this->getScopeKey($model);

        if (isset($this->scopedSettings[$key])) {
            $this->scopedSettings[$key]->clearCache();
            unset($this->scopedSettings[$key]);
        }
    }

    /**
     * Clear all cached scopes and their settings.
     *
     * Useful for queue workers processing multiple jobs
     * in the same process, to prevent stale settings data.
     *
     * @return void
     */
    public function clearAllScopes (): void
    {
        $this->scopedSettings = [];
    }

    /**
     * Build a unique cache key for a scoped model.
     *
     * @param Model $model
     *
     * @return string
     */
    protected function getScopeKey (Model $model): string
    {
        return get_class($model) . ':' . $model->getKey();
    }

    /**
     * @param string $method
     * @param array $arguments
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function __call ($method, $arguments)
    {
        try {
            return $this->forwardCallTo($this->settings, $method, $arguments);
        } catch (Error|BadMethodCallException $e) {
            foreach ($this->scopedSettings as $class => $container) {
                // Extract class part from "ClassName:id" key
                $className = explode(':', $class)[0];
                $shortName = class_basename($className);

                if (strtolower($shortName) === $method) {
                    return $container;
                }
            }

            if (!empty($arguments) && is_object($arguments[0]) && $arguments[0] instanceof Model) {
                return $this->scope($arguments[0]);
            }

            throw new \Exception('Tried to access scope which isn\'t initialized');
        }
    }
}
