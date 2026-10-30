<?php

return [
    'version' => '1.3.0',
    'backup_directory' => storage_path('app/private/backups'),
    'backup_retention_days' => 30,
    'admin_password' => env('SALON_ADMIN_PASSWORD'),
    'demo_urls' => [
        'salon' => env('SALON_DEMO_SALON_URL'),
        'barber' => env('SALON_DEMO_BARBER_URL'),
        'nails' => env('SALON_DEMO_NAILS_URL'),
    ],
    'demo_sales_contact' => env('SALON_DEMO_SALES_CONTACT'),
];
