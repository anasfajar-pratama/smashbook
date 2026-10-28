<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Pemilik GOR', 'email' => 'owner@smasharena.id', 'phone' => '0812-0000-0001', 'role' => 'owner'],
            ['name' => 'Admin GOR', 'email' => 'admin@smasharena.id', 'phone' => '0812-0000-0002', 'role' => 'admin'],
            ['name' => 'Dewi Kartika', 'email' => 'dewi@smasharena.id', 'phone' => '0812-0000-0003', 'role' => 'kasir'],
            ['name' => 'Rudi Hartono', 'email' => 'rudi@smasharena.id', 'phone' => '0812-0000-0004', 'role' => 'kasir'],
            ['name' => 'Andi Saputra', 'email' => 'operator@smasharena.id', 'phone' => '0812-0000-0005', 'role' => 'operator'],
        ];

        foreach ($users as $data) {
            User::query()->updateOrCreate(
                ['email' => $data['email']],
                $data + [
                    'password' => 'smashbook123',
                    'pin_hash' => Hash::make('1234'),
                    'is_active' => true,
                ]
            );
        }
    }
}
