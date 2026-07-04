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
                'name' => 'Saroath Farvees',
                'email' => 'farvees@gmail.com',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'password' => Hash::make('password'),
                'remember_token' => null
            ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
