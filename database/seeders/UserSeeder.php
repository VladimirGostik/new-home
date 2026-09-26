<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                'locale' => 'sk',
            ],
        );

        $admin->assignRole('admin');

        $owner = User::firstOrCreate(
            ['email' => 'gostikvladko9@gmail.com'],
            [
                'name' => 'Vladimír',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                'locale' => 'sk',
            ],
        );

        $owner->assignRole('user');
    }
}
