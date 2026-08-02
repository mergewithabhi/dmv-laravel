<?php

namespace App\Console\Commands;

use App\Services\StaticSiteImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dmv:import-static {--reset-content : Replace current section values with the legacy defaults}')]
#[Description('Import the legacy DMV Warriors site into the Laravel CMS')]
class ImportStaticSite extends Command
{
    public function handle(StaticSiteImporter $importer): int
    {
        $this->info('Importing media, content, and operational records...');
        $counts = $importer->run((bool) $this->option('reset-content'));

        $this->table(['Resource', 'Count'], collect($counts)->map(
            fn ($count, $resource) => [$resource, $count]
        )->values()->all());

        $this->info('DMV Warriors CMS content is ready.');

        return self::SUCCESS;
    }
}
