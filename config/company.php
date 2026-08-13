<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company Identity (Kop Surat)
    |--------------------------------------------------------------------------
    |
    | Official identity used in generated procurement documents (Surat
    | Penawaran Harga, BAST, and Invoice). Update these values to match the
    | legal entity operating the platform.
    |
    */

    'name' => env('COMPANY_NAME', 'PT. Pengadaan Barang Nusantara'),

    'legal_entity' => env('COMPANY_LEGAL_ENTITY', 'PT. Pengadaan Barang Nusantara'),

    'nib' => env('COMPANY_NIB', '1234567890123'),

    'pkp' => env('COMPANY_PKP', 'PKP'),

    'npwp' => env('COMPANY_NPWP', '00.000.000.0-000.000'),

    'address' => env('COMPANY_ADDRESS', 'Jl. Contoh No. 123, Jakarta Pusat, DKI Jakarta 10110'),

    'phone' => env('COMPANY_PHONE', '+62 21 0000 0000'),

    'email' => env('COMPANY_EMAIL', 'info@pengadaan.example'),

    'website' => env('COMPANY_WEBSITE', 'https://pengadaan.example'),

    'bank' => [
        'name' => env('COMPANY_BANK_NAME', 'Bank Contoh'),
        'account_name' => env('COMPANY_BANK_ACCOUNT_NAME', 'PT. Pengadaan Barang Nusantara'),
        'account_number' => env('COMPANY_BANK_ACCOUNT_NUMBER', '0000-0000-0000-000'),
        'branch' => env('COMPANY_BANK_BRANCH', 'Jakarta Pusat'),
    ],
];
