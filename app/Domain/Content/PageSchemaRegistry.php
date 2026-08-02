<?php

namespace App\Domain\Content;

use InvalidArgumentException;

class PageSchemaRegistry
{
    public function all(): array
    {
        return config('cms.templates', []);
    }

    public function get(string $templateKey): array
    {
        $schema = $this->all()[$templateKey] ?? null;

        if (! $schema) {
            throw new InvalidArgumentException("Unknown page template [{$templateKey}].");
        }

        return $schema;
    }

    public function templatePath(string $templateKey): string
    {
        return resource_path('site-templates/'.$this->get($templateKey)['file']);
    }
}
