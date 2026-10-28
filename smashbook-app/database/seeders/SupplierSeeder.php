<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'UD Sumber Rejeki', 'contact_person' => 'Bu Yanti', 'phone' => '0812-2233-4455', 'address' => 'Bandung', 'category' => 'Minuman', 'payment_term' => 'COD / tempo 7 hari'],
            ['name' => 'PT Sport Sarana', 'contact_person' => 'Pak Ridwan', 'phone' => '021-7788-9900', 'address' => 'Jakarta', 'category' => 'Shuttlecock & raket', 'payment_term' => 'Tempo 14 hari'],
            ['name' => 'CV Terang Elektrik', 'contact_person' => 'Pak Hendra', 'phone' => '0813-4455-6677', 'address' => 'Bandung', 'category' => 'Peralatan & jasa', 'payment_term' => 'Tempo 14 hari'],
            ['name' => 'Warung Bu Tini', 'contact_person' => 'Bu Tini', 'phone' => '0857-1122-3344', 'address' => 'Depan GOR', 'category' => 'Minuman', 'payment_term' => 'Tunai'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(['name' => $supplier['name']], $supplier);
        }
    }
}
