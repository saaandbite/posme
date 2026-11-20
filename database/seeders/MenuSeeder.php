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
                ['Kopi Tubruk', 'Diseduh pekat, diminum pelan. Kopi hitam tanpa ribet.', 10000, 'images/menu/kopi-tubruk.jpg'],
                ['Es Kopi Susu', 'Espresso, susu segar, gula aren. Manisnya pas, dinginnya nendang.', 15000, 'images/menu/es-kopi-susu.jpg'],
                ['Teh Manis', 'Teh melati hangat, gula secukupnya. Teman ngobrol paling aman.', 8000, 'images/menu/teh-manis.webp'],
                ['Es Teh', 'Teh melati dingin, segar. Haus hilang, dompet tetap aman.', 8000, 'images/menu/es-teh.jpg'],
                ['Jus Alpukat', 'Alpukat mentega diblender dengan susu. Kental dan bikin kenyang.', 15000, 'images/menu/jus-alpukat.jpg'],
            ],
            'Makanan' => [
                ['Nasi Goreng', 'Nasi goreng telur, ayam suwir, kerupuk. Porsi warung, rasa rumahan.', 18000, 'images/menu/nasi-goreng.jpg'],
                ['Mie Goreng', 'Mie goreng sayur dan telur. Pedasnya bisa diatur, tinggal bilang.', 15000, 'images/menu/mie-goreng.jpg'],
                ['Ayam Geprek', 'Ayam crispy digeprek sambal bawang. Buat yang doyan pedas.', 15000, 'images/menu/ayam-geprek.webp'],
                ['Kentang Goreng', 'Kentang goreng renyah, saus jangan sampai lupa.', 12000, 'images/menu/kentang-goreng.jpg'],
                ['Pisang Goreng', 'Pisang raja digoreng hangat, taburan gula. Manisnya sederhana.', 10000, 'images/menu/pisang-goreng.jpg'],
            ],
            'Snack' => [
                ['Roti Bakar', 'Roti bakar coklat keju, hangat dan lumer.', 12000, 'images/menu/roti-bakar.jpg'],
                ['Singkong Goreng', 'Singkong goreng gurih, sambal di samping. Camilan paling jujur.', 10000, 'images/menu/singkong-goreng.jpg'],
            ],
        ];

        $sort = 0;
        foreach ($menus as $categoryName => $items) {
            $category = Category::updateOrCreate(
                ['name' => $categoryName],
                ['sort_order' => $sort++, 'is_active' => true]
            );

            $itemSort = 0;
            foreach ($items as [$name, $description, $price, $image]) {
                MenuItem::updateOrCreate(
                    ['name' => $name],
                    [
                        'category_id' => $category->id,
                        'description' => $description,
                        'price' => $price,
                        'image' => $image,
                        'is_available' => true,
                        'is_active' => true,
                        'sort_order' => $itemSort++,
                    ]
                );
            }
        }
    }
}
