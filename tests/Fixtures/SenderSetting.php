<?php

namespace Npabisz\LaravelSettings\Tests\Fixtures;

use Npabisz\LaravelSettings\Models\BaseSetting;

class SenderSetting extends BaseSetting
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $company = '';

    public function fromArray(array $data): void
    {
        $this->name = $data['name'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->phone = $data['phone'] ?? '';
        $this->company = $data['company'] ?? '';
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
        ];
    }
}
