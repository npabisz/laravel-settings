# Settings for Laravel

Basic settings for Laravel 9+. Can be either global or morphed to models.

## Requirements
* PHP >= 8.1
* Laravel >= 9.0

## Installation

```bash
composer require npabisz/laravel-settings
```

Then publish vendor resources and migration

```bash
php artisan vendor:publish --provider="Npabisz\LaravelSettings\SettingsServiceProvider"
```

Finally, you should run migration

```bash
php artisan migrate
```

## Basic usage

```php
use Npabisz\LaravelSettings\Facades\Settings;

// Get global website setting value
$value = Settings::get('api_mode');

// Update global website setting value
Settings::set('api_mode', 'production');

// Access to the Setting model
$settingModel = Settings::setting('api_mode');
$settingModel->delete();

// Get all global website settings models
$settingModels = Settings::all();

// Get all global website settings models,
// but filling the missing ones with default values
$settingModels = Settings::allWithDefaults();

foreach ($settingModels as $setting) {
    if (null === $setting->id) {
        // This one isn't existing in database
        // and has default value based on definition
    }
}
```

## Scoping to models

```php
use Npabisz\LaravelSettings\Facades\Settings;

// Local scope for model
$model = User::first();
$userSettings = Settings::scope($model);

// Get user setting value
$value = $userSettings->get('is_newsletter_opted_in');

// Set user setting value
$userSettings->set('is_newsletter_opted_in', true);

// You can use any model which implements HasSettings trait
// Local scope for article
$article = Article::first();
$articleSettings = Settings::scope($article);

// Get article setting value
$articleSettings->get('enable_promo_banner');

// Set article setting value
$articleSettings->set('enable_promo_banner', true);
```

## Using global scopes

It easier to use global scope for models that won't change during request, eg. logged in user. Which is globally scoped by default, but here is example how to do it manually, eg. you need to persist scope in command.

```php
use Npabisz\LaravelSettings\Facades\Settings;

// Global scope for model
$user = User::first();
Settings::scopeGlobal($user);

// Now you can call magic method and
// it will return SettingsContainer
// for scoped user
Settings::user()->get('is_gamer');
Settings::user()->set('is_gamer', false);

// Replace scope
$anotherUser = User::find(2);
Settings::scopeGlobal($anotherUser);

// Now settings returned by user()
// method belongs to $anotherUser
Settings::user()->set('is_gamer', true);

// You can scope any model which
// has HasSettings trait
$article = Article::first();
Settings::scopeGlobal($article);

// Now you can access them via
// magic method named after class name
Settings::article()->get('is_premium');
```

## Settings definitions

Every setting has to have definition. This way it is always of the same type and can have default values. Settings definitions are declared under static method `getSettingsDefinitions`. 

> ### Remember
> Global settings are defined on `Setting` model.

```php
use Npabisz\LaravelSettings\Models\AbstractSetting;

class Setting extends AbstractSetting
{
    /**
     * @return array
     */
    public static function getSettingsDefinitions (): array
    {
        return [
            [
                // Setting name which will be unique
                'name' => 'api_mode',
                // Default value for setting
                'default' => 'sandbox',
                // You can optionally specify valid values
                'options' => [
                    'production',
                    'sandbox',
                ],
            ],
            [
                // Another setting name
                'name' => 'is_enabled',
                // You can optionally specify setting type
                'type' => 'bool',
                // Default value for setting
                'default' => false,
            ],
            [
                // Another setting name
                'name' => 'address',
                // You can use classes which will be stored as json
                'type' => Address::class,
            ],
        ];
    }
}
```

Instead of storing each field of address in separate setting. You can use class which will be then casted to json.

```php
use Npabisz\LaravelSettings\Models\BaseSetting;

class Address extends BaseSetting
{
    /**
     * @var string 
     */
    public string $street;
    
    /**
     * @var string 
     */
    public string $zipcode;
    
    /**
     * @var string 
     */   
    public string $city;
    
    public function __construct ()
    {
        // You can specify default values
        $this->street = '';
        $this->zipcode = '';
        $this->city = '';
    }
    
    /**
     * This method will be used to populate
     * data from json object.
     * 
     * @param array $data
     */
    public function fromArray (array $data)
    {
        $this->street = $data['street'] ?? '';
        $this->zipcode = $data['zipcode'] ?? '';
        $this->city = $data['city'] ?? '';
    }

    /**
     * @return array
     */
    public function toArray (): array
    {
        return [
            'street' => $this->street,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
        ];
    }
}
```

## Models

If you want to have settings on model you need to use `HasSettings` trait and declare some settings definitions.

```php
use Npabisz\LaravelSettings\Traits\HasSettings;

class User extends Authenticatable
{
    use HasSettings;
    
    ...

    /**
     * @return array
     */
    public static function getSettingsDefinitions(): array
    {
        return [
            [
                'name' => 'theme_mode',
                'default' => 'light',
                'options' => [
                    'light',
                    'dark',
                ],
            ],
            [
                'name' => 'is_gamer',
                'type' => 'bool',
            ],
            [
                'name' => 'games_count',
                'type' => 'int',
            ],
        ];
    }
}
```

Now you can get their settings.

```php
use Npabisz\LaravelSettings\Facades\Settings;

// Assuming user is logged in
Settings::user()->get('is_gamer');
Settings::user()->set('games_count', 10);

// You can also access settings via property
$user->settings->get('is_gamer');
$user->settings->set('games_count', 10);
```

## License

`npabisz/laravel-settings` is released under the MIT License. See the bundled [LICENSE](./LICENSE) for details.