<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $table = DiningTable::updateOrCreate(
                ['name' => 'Meja '.$i],
                ['code' => 'MEJA'.$i, 'is_active' => true]
            );

            // Meja lama (seed sebelum token ada) dapat token acak sekali
            // saja; token yang sudah ada tidak pernah diubah di sini.
            // Meja baru mendapat token otomatis dari event creating.
            // (Set langsung + save — token sengaja tidak fillable.)
            if (empty($table->token)) {
                $table->token = DiningTable::generateToken();
                $table->save();
            }
        }
    }
}
