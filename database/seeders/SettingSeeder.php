<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::updateOrCreate(
            ['id' => 1],
            [
                'restaurant_name' => 'Posme Cafe',
                'tax_percent' => 0,
                'service_percent' => 0,
                'accept_cash' => true,
                'accept_qris' => true,
            ]
        );
    }
}
