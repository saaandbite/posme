<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin Posme', 'email' => 'admin@posme.test', 'role' => UserRole::Admin],
            ['name' => 'Kasir Posme', 'email' => 'kasir@posme.test', 'role' => UserRole::Cashier],
            ['name' => 'Dapur Posme', 'email' => 'dapur@posme.test', 'role' => UserRole::Kitchen],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [...$data, 'password' => 'password']
            );
        }
    }
}
