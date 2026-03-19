<?php

namespace Npabisz\LaravelSettings\Tests\Fixtures;

/**
 * A model that inherits HasSettings from User (parent class).
 * Used to test class_uses_recursive detection.
 */
class AdminUser extends User
{
    protected $table = 'users';

    // Does NOT declare `use HasSettings` — inherits it from User
    // Does NOT override getSettingsDefinitions — inherits from User
}
