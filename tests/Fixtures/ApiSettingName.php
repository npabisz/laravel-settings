<?php

namespace Npabisz\LaravelSettings\Tests\Fixtures;

enum ApiSettingName: string
{
    case AppEnabled = 'app_enabled';
    case ApiMode = 'api_mode';
    case ApiKey = 'api_key';
    case Currency = 'currency';
}
