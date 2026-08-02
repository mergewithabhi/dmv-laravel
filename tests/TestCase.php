<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setEnvironmentVariable('CMS_ADMIN_NAME', 'Test Administrator');
        $this->setEnvironmentVariable('CMS_ADMIN_EMAIL', 'admin@dmv-warriors.test');
        $this->setEnvironmentVariable('CMS_ADMIN_PASSWORD', 'Test-Only-Admin-Password-2026!');
    }

    private function setEnvironmentVariable(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
