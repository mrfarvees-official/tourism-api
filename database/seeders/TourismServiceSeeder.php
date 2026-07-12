<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TourismService;
use Illuminate\Database\Seeder;

class TourismServiceSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('key', 'lanka-trails')->first();

        if (!$tenant) {
            return;
        }

        foreach (TourismService::defaultSeedRows() as $row) {
            TourismService::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => $row['slug'],
            ], array_merge($row, ['tenant_id' => $tenant->id]));
        }
    }
}
