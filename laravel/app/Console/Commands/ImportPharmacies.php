<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\LocationCategory;
use App\Osm\PharmacyImporter;
use Illuminate\Console\Command;

class ImportPharmacies extends Command
{
    protected $signature = 'map:import-pharmacies
        {--district=Süleymanpaşa : District (ilçe) name as it appears in the districts table}
        {--dry-run : Show what would be imported without writing anything}';

    protected $description = 'Import pharmacies from OpenStreetMap into the locations table';

    public function handle(PharmacyImporter $importer): int
    {
        $districtName = (string) $this->option('district');

        $district = District::query()->where('title', $districtName)->first();
        if ($district === null) {
            $this->error("District '{$districtName}' not found in the districts table.");

            return self::FAILURE;
        }

        $category = LocationCategory::query()->where('title', 'Eczane')->first();
        if ($category === null) {
            $this->error("Category 'Eczane' not found — run `php artisan db:seed` first.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[DRY RUN] ' : '')."Fetching pharmacies in {$districtName} from OpenStreetMap…");

        $result = $importer->import($districtName, $district->id, $category->id, $dryRun);

        foreach ($result['created'] as $title) {
            $this->line("  + {$title}");
        }
        foreach ($result['updated'] as $title) {
            $this->line("  ~ {$title} (already imported, refreshed)");
        }

        $this->info(sprintf(
            '%s%d new, %d updated, %d skipped (unnamed).',
            $dryRun ? '[DRY RUN — nothing written] ' : '',
            count($result['created']),
            count($result['updated']),
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
