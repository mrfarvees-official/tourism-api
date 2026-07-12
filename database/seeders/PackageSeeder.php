<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TourismPackage;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('key', 'lanka-trails')->first();

        if (!$tenant) {
            return;
        }

        foreach (TourismPackage::defaultSeedRows() as $row) {
            TourismPackage::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => $row['slug'],
            ], array_merge($row, ['tenant_id' => $tenant->id]));
        }
    }
}
