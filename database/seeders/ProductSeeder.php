<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Sampel Produk B2G / B2B Spesifik
        $sampleProducts = [
            [
                'sku' => 'LAP-TKDN-001',
                'title' => 'Laptop Opsional B2G 14 Inci Core i5 (TKDN 43.5%)',
                'slug' => 'laptop-opsional-b2g-14-inci-core-i5',
                'description' => 'Laptop pengadaan instansi pemerintah dengan sertifikasi TKDN tinggi, RAM 16GB, SSD 512GB, OS Windows 11 Pro.',
                'base_price' => 12500000.00,
                'margin_percentage' => 8.00,
                'tax_rate_percentage' => 11.00,
                'estimated_shipping' => 150000.00,
                'tkdn_percentage' => 43.50,
                'is_sni' => true,
                'warranty_info' => '2 Tahun Garansi Resmi On-Site',
                'datasheet_url' => 'https://example.com/datasheet/laptop-b2g.pdf',
                'stock' => 50,
            ],
            [
                'sku' => 'SRV-RACK-002',
                'title' => 'Server Rack Mount 2U Dual Xeon 64GB RAM',
                'slug' => 'server-rack-mount-2u-dual-xeon-64gb-ram',
                'description' => 'Server enterprise untuk infrastruktur data center B2B dan e-Katalog, support Hot-swappable Redundant PSU.',
                'base_price' => 45000000.00,
                'margin_percentage' => 10.00,
                'tax_rate_percentage' => 11.00,
                'estimated_shipping' => 500000.00,
                'tkdn_percentage' => 28.00,
                'is_sni' => true,
                'warranty_info' => '3 Tahun Garansi Server Enterprise',
                'datasheet_url' => 'https://example.com/datasheet/server-rack.pdf',
                'stock' => 15,
            ],
            [
                'sku' => 'UPS-3000VA-003',
                'title' => 'UPS Online Double Conversion 3000VA / 2700W',
                'slug' => 'ups-online-double-conversion-3000va',
                'description' => 'Uninterruptible Power Supply untuk perlindungan server dan perangkat medis instansi.',
                'base_price' => 8500000.00,
                'margin_percentage' => 12.00,
                'tax_rate_percentage' => 11.00,
                'estimated_shipping' => 200000.00,
                'tkdn_percentage' => 35.10,
                'is_sni' => true,
                'warranty_info' => '2 Tahun Garansi Unit & Baterai',
                'datasheet_url' => 'https://example.com/datasheet/ups-3000va.pdf',
                'stock' => 30,
            ],
        ];

        foreach ($sampleProducts as $productData) {
            Product::updateOrCreate(['sku' => $productData['sku']], $productData);
        }

        // Tambah 10 sampel produk acak menggunakan Factory (idempotent)
        if (Product::where('sku', 'like', 'PRD-%')->doesntExist()) {
            Product::factory(10)->create();
        }
    }
}
