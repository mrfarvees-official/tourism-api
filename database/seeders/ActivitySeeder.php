<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TourismActivity;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('key', 'lanka-trails')->first();

        if (!$tenant) {
            return;
        }

        foreach (TourismActivity::defaultSeedRows() as $row) {
            TourismActivity::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => $row['slug'],
            ], array_merge($row, ['tenant_id' => $tenant->id]));
        }
    }
}
