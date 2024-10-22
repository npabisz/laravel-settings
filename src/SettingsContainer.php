<?php

namespace Npabisz\LaravelSettings;

use Npabisz\LaravelSettings\Models\BaseSetting;
use Npabisz\LaravelSettings\Traits\HasSettings;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class SettingsContainer
{
    /**
     * @var bool
     */
    protected bool $isScoped = false;

    /**
     * @var bool
     */
    protected bool $isGlobalScoped = false;

    /**
     * @var ?Model
     */
    protected ?Model $scopedModel = null;

    /**
     * @var string
     */
    protected string $scopedClass = '';

    /**
     * @var ?Collection
     */
    protected ?Collection $cachedSettings = null;

    /**
     * @var ?Collection
     */
    protected ?Collection $cachedSettingsWithDefaults = null;

    /**
     * @var bool
     */
    protected bool $cacheSettings = true;

    /**
     * @param ?Model $scopeModel
     * @param bool $isGlobalScoped
     *
     * @throws \Exception
     */
    public function __construct (?Model $scopeModel = null, bool $isGlobalScoped = false)
    {
        if ($scopeModel !== null) {
            $this->checkMethods($scopeModel);
            $this->isScoped = true;
            $this->isGlobalScoped = $isGlobalScoped;
            $this->scopedModel = $scopeModel;
            $this->scopedClass = get_class($scopeModel);
        }
    }

    /**
     * @param Model $scopeModel
     *
     * @throws \Exception
     *
     * @return void
     */
    protected function checkMethods (Model $scopeModel): void
    {
        if (!in_array(
            HasSettings::class,
            array_keys((new \ReflectionClass($scopeModel::class))->getTraits())
        )) {
            throw new \Exception('Model ' . get_class($scopeModel) . ' have to use ' . HasSettings::class . ' trait');
        }

        if (!is_array($scopeModel::getSettingsDefinitions())) {
            throw new \Exception('Model ' . get_class($scopeModel) . '::getSettingsDefinitions has to return array');
        }
    }

    /**
     * @return array
     */
    protected function getDefinitions (): array
    {
        if (!$this->isScoped) {
            return Setting::getGlobalSettingsDefinitions();
        }

        return $this->scopedClass::getSettingsDefinitions();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    protected function getDefaults (): \Illuminate\Support\Collection
    {
        $settings = [];

        foreach ($this->getDefinitions() as $definition) {
            $settings[] = new Setting([
                'settingable_id' => null,
                'settingable_type' => $this->isScoped ? $this->scopedClass : null,
                'name' => $definition['name'],
                'value' => $definition['default'] ?? null,
            ]);
        }

        return collect($settings);
    }

    /**
     * @param \BackedEnum|string $name
     *
     * @return string
     */
    protected function castSettingName (\BackedEnum|string $name): string
    {
        return $name instanceof \BackedEnum
            ? $name->value
            : $name;
    }

    /**
     * @param Collection $collection
     *
     * @return Collection
     */
    protected function appendDefaults (Collection $collection): Collection
    {
        foreach ($this->getDefaults() as $default) {
            if ($collection->where('name', $this->castSettingName($default->name))->first()) {
                continue;
            }

            $collection->add($default);
        }

        return $collection;
    }

    /**
     * @return void
     */
    public function clearCache (): void
    {
        $this->cachedSettings = null;
        $this->cachedSettingsWithDefaults = null;
    }

    /**
     * @return void
     */
    protected function clearCachedDefaults (): void
    {
        $this->cachedSettingsWithDefaults = null;
    }

    /**
     * @param \BackedEnum|string $name
     *
     * @return bool
     */
    public function isValidSettingName (\BackedEnum|string $name): bool
    {
        foreach ($this->getDefinitions() as $definition) {
            if ($this->castSettingName($definition['name']) === $this->castSettingName($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \BackedEnum|string $name
     * @param mixed $value
     *
     * @return bool
     */
    public function isValidSettingValue (\BackedEnum|string $name, mixed $value): bool
    {
        if ($value === null) {
            foreach ($this->getDefinitions() as $definition) {
                if ($this->castSettingName($definition['name']) === $this->castSettingName($name)) {
                    if (isset($definition['is_nullable']) && $definition['is_nullable'] === true) {
                        return true;
                    }

                    return false;
                }
            }
        }

        $options = $this->getSettingOptions($name);

        if (!empty($options)) {
            $options = array_map(function ($option) {
                return $option instanceof \BackedEnum
                    ? $option->value
                    : $option;
            }, $options);

            $value = $value instanceof \BackedEnum
                ? $value->value
                : $value;

            return in_array($value, $options);
        }

        return true;
    }

    /**
     * @param ?Model $model
     *
     * @return bool
     */
    public function isScopedTo (?Model $model = null): bool
    {
        return $model && $this?->scopedModel === $model;
    }

    /**
     * @param \BackedEnum|string $name
     *
     * @return array
     */
    public function getSettingOptions (\BackedEnum|string $name): array
    {
        foreach ($this->getDefinitions() as $definition) {
            if ($this->castSettingName($definition['name']) === $this->castSettingName($name)) {
                if (!empty($definition['enum'])) {
                    return array_map(function ($item) use ($definition) {
                        if ((new \ReflectionEnum($definition['enum']))->isBacked()) {
                            return $item->value;
                        } else {
                            return $item->name;
                        }
                    }, $definition['enum']::cases());
                }

                if (isset($definition['options'])) {
                    return $definition['options'];
                }
            }
        }

        return [];
    }

    /**
     * @param \BackedEnum|string $name
     *
     * @throws \Exception
     *
     * @return Setting|null
     */
    public function setting (\BackedEnum|string $name): ?Setting
    {
        if ($name instanceof \BackedEnum) {
            $reflection = new \ReflectionEnum($name);

            if ((string) $reflection->getBackingType() !== 'string') {
                throw new \Exception('Only BackedEnum of string type can be used as setting name');
            }
        }

        if (!$this->isValidSettingName($name)) {
            if ($this->isScoped) {
                throw new \Exception($this->scopedClass . ' setting definition does not exists for "' . $name . '"');
            } else {
                throw new \Exception('Global setting definition does not exists for "' . $name . '"');
            }
        }

        if ($this->cacheSettings) {
            $this->all();

            return $this->cachedSettings
                ->where('name', $this->castSettingName($name))
                ->first();
        }

        if ($this->isScoped) {
            return Setting::where('settingable_id', $this->scopedModel->id)
                ->where('settingable_type', $this->scopedClass)
                ->where('name', $this->castSettingName($name))
                ->first();
        }

        return Setting::whereNull('settingable_id')
            ->whereNull('settingable_type')
            ->where('name', $this->castSettingName($name))
            ->first();
    }

    /**
     * @param \BackedEnum|string $name
     * @param mixed|null $default
     * @param bool $returnDefaultCast
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function get (\BackedEnum|string $name, mixed $default = null, bool $returnDefaultCast = true): mixed
    {
        $setting = $this->setting($name);
        $settingDefinition = [];

        foreach ($this->getDefinitions() as $definition) {
            if ($this->castSettingName($definition['name']) === $this->castSettingName($name)) {
                $settingDefinition = $definition;

                break;
            }
        }

        if ($setting?->id === null) {
            if ($returnDefaultCast
                && !empty($settingDefinition['cast'])
                && is_subclass_of($settingDefinition['cast'], BaseSetting::class)
            ) {
                $setting = new Setting([
                    'settingable_id' => null,
                    'settingable_type' => $this->isScoped ? $this->scopedClass : null,
                    'name' => $settingDefinition['name'],
                    'value' => $settingDefinition['default'] ?? null,
                ]);

                return $setting->value;
            }

            return $default ?: $settingDefinition['default'] ?? null;
        }

        return $setting->value;
    }

    /**
     * @param \BackedEnum|string $name
     * @param mixed $value
     *
     * @throws \Exception
     *
     * @return Setting
     */
    public function set (\BackedEnum|string $name, mixed $value): Setting
    {
        $setting = $this->setting($name);

        if (!$this->isValidSettingValue($name, $value)) {
            throw new \Exception('Invalid setting value for "' . $name . '"');
        }

        if ($setting) {
            $setting->value = $value;
            $setting->save();
        } else {
            $setting = Setting::create([
                'settingable_id' => $this->isScoped ? $this->scopedModel->id : null,
                'settingable_type' => $this->isScoped ? $this->scopedClass : null,
                'name' => $name,
                'value' => $value,
            ]);
            $setting = Setting::find($setting->id);

            if ($this->cacheSettings && $this->cachedSettings) {
                $this->cachedSettings->add($setting);
                $this->clearCachedDefaults();
            }
        }

        return $setting;
    }

    /**
     * @return Collection
     */
    public function all (): Collection
    {
        if ($this->cacheSettings && $this->cachedSettings) {
            return $this->cachedSettings;
        }

        if ($this->isScoped) {
            $this->cachedSettings = Setting::where('settingable_id', $this->scopedModel->id)
                ->where('settingable_type', $this->scopedClass)
                ->get();

            return $this->cachedSettings;
        }

        $this->cachedSettings = Setting::whereNull('settingable_id')
            ->whereNull('settingable_type')
            ->get();

        return $this->cachedSettings;
    }

    /**
     * @return Collection
     */
    public function allWithDefaults (): Collection
    {
        if ($this->cacheSettings && $this->cachedSettingsWithDefaults) {
            return $this->cachedSettingsWithDefaults;
        }

        $this->cachedSettingsWithDefaults = $this->appendDefaults($this->all());

        return $this->cachedSettingsWithDefaults;
    }

    /**
     * Do not use caching for settings
     *
     * @return $this
     */
    public function noCache (): SettingsContainer
    {
        $this->cacheSettings = false;

        return $this;
    }

    /**
     * Use caching for settings
     *
     * @return $this
     */
    public function cache (): SettingsContainer
    {
        $this->cacheSettings = true;

        return $this;
    }
}
