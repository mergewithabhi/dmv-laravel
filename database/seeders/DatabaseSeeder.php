<?php

namespace Database\Seeders;

use App\Services\StaticSiteImporter;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(StaticSiteImporter::class)->run();
    }
}
