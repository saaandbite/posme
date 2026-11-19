<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            'Minuman' => [
                ['Kopi Tubruk', 'Kopi hitam tubruk khas warung kopi.', 10000],
                ['Es Kopi Susu', 'Espresso, susu segar, dan gula aren.', 15000],
                ['Teh Manis', 'Teh melati hangat dengan gula.', 8000],
                ['Es Teh', 'Teh melati dingin, segar.', 8000],
                ['Jus Alpukat', 'Alpukat mentega diblender dengan susu.', 15000],
            ],
            'Makanan' => [
                ['Nasi Goreng', 'Nasi goreng telur, ayam suwir, dan kerupuk.', 18000],
                ['Mie Goreng', 'Mie goreng sayur dan telur.', 15000],
                ['Ayam Geprek', 'Ayam crispy digeprek dengan sambal bawang.', 15000],
                ['Kentang Goreng', 'Kentang goreng renyah dengan saus.', 12000],
                ['Pisang Goreng', 'Pisang raja goreng dengan taburan gula.', 10000],
            ],
            'Snack' => [
                ['Roti Bakar', 'Roti bakar coklat keju.', 12000],
                ['Singkong Goreng', 'Singkong goreng gurih dengan sambal.', 10000],
            ],
        ];

        $sort = 0;
        foreach ($menus as $categoryName => $items) {
            $category = Category::updateOrCreate(
                ['name' => $categoryName],
                ['sort_order' => $sort++, 'is_active' => true]
            );

            $itemSort = 0;
            foreach ($items as [$name, $description, $price]) {
                MenuItem::updateOrCreate(
                    ['name' => $name],
                    [
                        'category_id' => $category->id,
                        'description' => $description,
                        'price' => $price,
                        'is_available' => true,
                        'is_active' => true,
                        'sort_order' => $itemSort++,
                    ]
                );
            }
        }
    }
}
