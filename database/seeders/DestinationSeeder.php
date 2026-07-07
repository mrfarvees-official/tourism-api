<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('key', 'lanka-trails')->first();

        if (!$tenant) {
            return;
        }

        foreach (Destination::defaultSeedRows() as $row) {
            Destination::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'slug' => $row['slug'],
                ],
                array_merge($row, [
                    'tenant_id' => $tenant->id,
                ]),
            );
        }
    }
}
