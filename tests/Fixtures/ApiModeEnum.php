<?php

namespace Npabisz\LaravelSettings\Tests\Fixtures;

enum ApiModeEnum: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';
}
