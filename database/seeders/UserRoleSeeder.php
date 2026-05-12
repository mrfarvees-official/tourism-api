<?php

namespace Database\Seeders;

use App\Models\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user_roles = [
            [
                'tenant_id' => null,
                'user_id' => 1,
                'role_id' => 1,
                'status' => 'active',
                'expires_at' => null
            ],
            [
                'tenant_id' => null,
                'user_id' => 2,
                'role_id' => 2,
                'status' => 'active',
                'expires_at' => null
            ],
        ];

        
        UserRole::truncate();
        
        UserRole::upsert($user_roles, [], []);
    }
}
