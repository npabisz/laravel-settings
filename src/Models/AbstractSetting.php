<?php

namespace Npabisz\LaravelSettings\Models;

use Npabisz\LaravelSettings\Traits\CastsToType;
use Npabisz\LaravelSettings\Traits\HasSettingsDefinitions;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

abstract class AbstractSetting extends Model
{
    use CastsToType,
        HasSettingsDefinitions;

    /**
     * @var string
     */
    protected $table = 'settings';

    /**
     * @var array
     */
    protected $fillable = [
        'settingable_id',
        'settingable_type',
        'name',
        'value',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'settingable_id' => 'integer',
    ];

    /**
     * @return MorphTo
     */
    public function settingable (): MorphTo
    {
        return $this->morphTo('settingsRelation');
    }

    /**
     * @return Attribute
     */
    public function value(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $definition = static::getSettingDefinition($attributes['name']);

                if ($attributes['settingable_type']) {
                    $definition = $attributes['settingable_type']::getSettingDefinition($attributes['name']);
                }

                if (!empty($definition['enum'])) {
                    return $this->castToType($definition['enum'], $value);
                }

                if (empty($definition['cast'])) {
                    return $value;
                }

                return $this->castToType($definition['cast'], $value);
            },
            set: function ($value, $attributes) {
                $definition = self::getSettingDefinition($attributes['name']);

                if ($attributes['settingable_type']) {
                    $definition = $attributes['settingable_type']::getSettingDefinition($attributes['name']);
                }

                if (!empty($definition['enum'])) {
                    $this->setAttributeByType($definition['enum'], 'value', $value);

                    return $this->attributes['value'];
                }

                if (empty($definition['cast'])) {
                    return $value;
                }

                $this->setAttributeByType($definition['cast'], 'value', $value);

                return $this->attributes['value'];
            }
        );
    }

    /**
     * @param \BackedEnum|string $name
     *
     * @return array|null
     */
    public static function getSettingDefinition (\BackedEnum|string $name): ?array
    {
        $definitions = static::getSettingsDefinitions();
        $stringName = $name instanceof \BackedEnum
            ? $name->value
            : $name;

        foreach ($definitions as $definition) {
            $definitionName = $definition['name'] instanceof \BackedEnum
                ? $definition['name']->value
                : $definition['name'];

            if ($definitionName === $stringName) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function getSettingOptions (): array
    {
        $definition = self::getSettingDefinition($this->name);

        if ($this->settingable_type) {
            $definition = $this->settingable_type::getSettingDefinition($this->name);
        }

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

        return [];
    }

    /**
     * @return array
     */
    public static function getGlobalSettingsDefinitions(): array
    {
        return static::getSettingsDefinitions();
    }

    /**
     * Merge the cast class attributes back into the model.
     *
     * @return void
     */
    protected function mergeAttributesFromClassCasts ()
    {
        foreach ($this->classCastCache as $key => $value) {
            if ($key === 'value') {
                $definition = self::getSettingDefinition($this->attributes['name']);

                if ($this->attributes['settingable_type']) {
                    $definition = $this->attributes['settingable_type']::getSettingDefinition($this->attributes['name']);
                }

                $caster = $this->resolveCasterClassByType($definition['cast'] ?? 'string');
            } else {
                $caster = $this->resolveCasterClass($key);
            }

            $this->attributes = array_merge(
                $this->attributes,
                $caster instanceof CastsInboundAttributes
                    ? [$key => $value]
                    : $this->normalizeCastClassResponse($key, $caster->set($this, $key, $value, $this->attributes))
            );
        }
    }
}
