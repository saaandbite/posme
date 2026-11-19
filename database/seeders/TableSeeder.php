<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            DiningTable::updateOrCreate(
                ['name' => 'Meja '.$i],
                ['code' => 'MEJA'.$i, 'is_active' => true]
            );
        }
    }
}
