<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin Posme', 'email' => 'admin@posme.test', 'role' => User::ROLE_ADMIN],
            ['name' => 'Kasir Posme', 'email' => 'kasir@posme.test', 'role' => User::ROLE_CASHIER],
            ['name' => 'Dapur Posme', 'email' => 'dapur@posme.test', 'role' => User::ROLE_KITCHEN],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [...$data, 'password' => 'password']
            );
        }
    }
}
