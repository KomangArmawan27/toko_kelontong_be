<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->shopOwner()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => UserRole::ShopOwner->value,
        ]);

        User::factory()->shopKeeper()->create([
            'name' => 'Shop Keeper',
            'email' => 'keeper@example.com',
        ]);

        User::factory()->customer()->create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
        ]);
    }
}
