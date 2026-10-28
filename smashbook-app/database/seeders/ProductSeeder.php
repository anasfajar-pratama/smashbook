<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $inventory = app(InventoryService::class);

        $products = [
            ['sku' => 'MNM-001', 'name' => 'Air Mineral 600ml', 'category' => 'Minuman', 'base_unit' => 'botol', 'purchase_unit' => 'dus', 'conversion_qty' => 24, 'cost_price' => 3100, 'sell_price' => 5000, 'stock' => 18, 'min_stock' => 48, 'reorder_point' => 72, 'icon' => '💧', 'location' => 'Kulkas'],
            ['sku' => 'MNM-002', 'name' => 'Air Mineral 1500ml', 'category' => 'Minuman', 'base_unit' => 'botol', 'purchase_unit' => 'dus', 'conversion_qty' => 12, 'cost_price' => 5200, 'sell_price' => 8000, 'stock' => 26, 'min_stock' => 12, 'reorder_point' => 24, 'icon' => '🚰', 'location' => 'Kulkas'],
            ['sku' => 'MNM-004', 'name' => 'Isotonik 500ml', 'category' => 'Minuman', 'base_unit' => 'botol', 'purchase_unit' => 'dus', 'conversion_qty' => 24, 'cost_price' => 5400, 'sell_price' => 8000, 'stock' => 9, 'min_stock' => 24, 'reorder_point' => 36, 'icon' => '🥤', 'location' => 'Kulkas'],
            ['sku' => 'MNM-006', 'name' => 'Kopi Sachet Panas', 'category' => 'Minuman', 'base_unit' => 'sachet', 'purchase_unit' => null, 'conversion_qty' => 1, 'cost_price' => 3800, 'sell_price' => 6000, 'stock' => 42, 'min_stock' => 20, 'reorder_point' => 30, 'icon' => '☕', 'location' => 'Etalase'],
            ['sku' => 'MNM-007', 'name' => 'Teh Kotak 300ml', 'category' => 'Minuman', 'base_unit' => 'kotak', 'purchase_unit' => 'dus', 'conversion_qty' => 24, 'cost_price' => 4800, 'sell_price' => 7000, 'stock' => 31, 'min_stock' => 12, 'reorder_point' => 24, 'icon' => '🍵', 'location' => 'Kulkas'],
            ['sku' => 'SHC-001', 'name' => 'Shuttlecock Yonex AS-30', 'category' => 'Shuttlecock', 'base_unit' => 'tube', 'purchase_unit' => 'tube', 'conversion_qty' => 1, 'cost_price' => 32000, 'sell_price' => 45000, 'stock' => 6, 'min_stock' => 10, 'reorder_point' => 16, 'icon' => '🏸', 'location' => 'Gudang'],
            ['sku' => 'SHC-004', 'name' => 'Shuttlecock Lokal (3 pcs)', 'category' => 'Shuttlecock', 'base_unit' => 'pack', 'purchase_unit' => 'pack', 'conversion_qty' => 1, 'cost_price' => 11500, 'sell_price' => 18000, 'stock' => 47, 'min_stock' => 20, 'reorder_point' => 30, 'icon' => '🏸', 'location' => 'Gudang'],
            ['sku' => 'AKS-002', 'name' => 'Grip Raket (per pcs)', 'category' => 'Aksesori', 'base_unit' => 'pcs', 'purchase_unit' => 'pack', 'conversion_qty' => 10, 'cost_price' => 7000, 'sell_price' => 12000, 'stock' => 28, 'min_stock' => 10, 'reorder_point' => 20, 'icon' => '🧤', 'location' => 'Etalase'],
            ['sku' => 'SEW-001', 'name' => 'Sewa Raket (per jam)', 'category' => 'Sewa', 'base_unit' => 'unit', 'purchase_unit' => null, 'conversion_qty' => 1, 'cost_price' => 0, 'sell_price' => 15000, 'stock' => 0, 'min_stock' => 0, 'reorder_point' => 0, 'icon' => '🎾', 'location' => 'Rak sewa', 'allow_negative' => true],
            ['sku' => 'JSA-001', 'name' => 'Jasa Stringing / Senar', 'category' => 'Jasa', 'base_unit' => 'layanan', 'purchase_unit' => null, 'conversion_qty' => 1, 'cost_price' => 42000, 'sell_price' => 65000, 'stock' => 0, 'min_stock' => 0, 'reorder_point' => 0, 'icon' => '🧵', 'location' => 'Meja stringing', 'allow_negative' => true],
            ['sku' => 'APP-001', 'name' => 'Kaos Smash Arena (M)', 'category' => 'Apparel', 'base_unit' => 'pcs', 'purchase_unit' => null, 'conversion_qty' => 1, 'cost_price' => 68000, 'sell_price' => 95000, 'stock' => 0, 'min_stock' => 5, 'reorder_point' => 8, 'icon' => '🧦', 'location' => 'Etalase'],
        ];

        foreach ($products as $data) {
            $opening = $data['stock'];
            unset($data['stock']);

            $product = Product::query()->firstOrNew(['sku' => $data['sku']]);
            $isNew = ! $product->exists;

            $product->fill($data + [
                'allow_negative' => $data['allow_negative'] ?? false,
                'is_active' => true,
            ]);

            if ($isNew) {
                $product->stock_qty = 0;
            }

            $product->save();

            if ($opening > 0 && ! $product->movements()->where('reason', 'stok_awal')->exists()) {
                $inventory->openingStock($product, $opening, (float) $product->cost_price, $admin);
            }
        }
    }
}
