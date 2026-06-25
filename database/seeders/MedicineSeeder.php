<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Size;
use Illuminate\Database\Seeder;

class MedicineSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['30C', '200C', '1M', 'Q', '6C', '30'];
        $sizes = ['30ml', '100ml', '2 dram', '1/2 oz', '1 dram'];

        $categoryModels = [];
        foreach ($categories as $name) {
            $categoryModels[$name] = Category::firstOrCreate(['name' => $name]);
        }

        $sizeModels = [];
        foreach ($sizes as $name) {
            $sizeModels[$name] = Size::firstOrCreate(['name' => $name]);
        }

        $medicinesData = [
            ['Arnica Montana', '30C', '30ml', 50],
            ['Arnica Montana', '200C', '30ml', 20],
            ['Nux Vomica', '30C', '2 dram', 15],
            ['Nux Vomica', '200C', '2 dram', 30],
            ['Lycopodium', '1M', '30ml', 10],
            ['Lycopodium', '30C', '30ml', 5],
            ['Sulphur', '200C', '1/2 oz', 8],
            ['Sulphur', 'Q', '100ml', 12],
            ['SL FOR 15 DAYS', '30C', '2 dram', 100],
            ['SL FOR 15 DAYS', '200C', '2 dram', 80],
        ];

        foreach ($medicinesData as [$name, $catName, $sizeName, $qty]) {
            $medicine = Medicine::firstOrCreate([
                'name' => $name,
            ]);

            MedicineStock::updateOrCreate(
                [
                    'medicine_id' => $medicine->id,
                    'category_id' => $categoryModels[$catName]->id,
                    'size_id' => $sizeModels[$sizeName]->id,
                ],
                [
                    'quantity' => $qty,
                    'quantity_reduction' => 1,
                ]
            );
        }
    }
}
