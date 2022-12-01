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
     * @param Model $model
     *
     * @throws \Exception
     *
     * @return SettingsContainer
     */
    public function scope (Model $model): SettingsContainer
    {
        $scoped = $this->scopedSettings[get_class($model)] ?? null;

        if ($scoped && $scoped->isScopedTo($model)) {
            return $scoped;
        }

        return new SettingsContainer($model);
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
        return $this->scopedSettings[get_class($model)] = new SettingsContainer($model, true);
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
                $shortName = (new \ReflectionClass($class))->getShortName();

                if (strtolower($shortName) === $method) {
                    return $this->scopedSettings[$class];
                }
            }

            if (!empty($arguments) && is_object($arguments[0]) && $arguments[0] instanceof Model) {
                return $this->scope($arguments[0]);
            }

            throw new \Exception('Tried to access scope which isn\'t initialized');
        }
    }
}
