<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => config('app.owner.name'),
                'email' => config('app.owner.email'),
                'email_verified_at' => date('Y-m-d H:i:s'),
                'password' => Hash::make(config('app.owner.password')),
                'remember_token' => null
            ],
            [
                'name' => 'John Wick',
                'email' => 'john@gmail.com',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'password' => Hash::make('password'),
                'remember_token' => null
            ]
        ];

        
        User::truncate();
        
        User::upsert($users, [], []);
    }
}
