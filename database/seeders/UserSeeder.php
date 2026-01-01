<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
          public function run(): void
    {
            User::create([
            'name' => 'Nevin Test',
            'email' => 'nevinshabat@test.com',
            'password' => Hash::make('12345678'),
            'profile_completed' => false,
            'role' => 'kr',
            'city_neighborhood' => 'Gaza',
            'wallet_type' => 'Ethereum',
            'wallet_address' => '0x1234567890abcdef1234567890abcdef12345678',
            'paypal_account' => 'nevinshabat@paypal.com',
        ]);
    }
}
