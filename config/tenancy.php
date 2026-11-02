<?php

use App\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;

$configuredCentralDomains = array_filter(array_map('trim', explode(',', env('TENANCY_CENTRAL_DOMAINS', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'))));
if (in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)) {
    $configuredCentralDomains = array_unique([...$configuredCentralDomains, 'localhost', '127.0.0.1', '::1']);
}

return [
    'tenant_model' => Tenant::class,
    'id_generator' => null,
    'domain_model' => Domain::class,
    'central_domains' => array_map('strtolower', $configuredCentralDomains),
    'base_domain' => env('TENANCY_BASE_DOMAIN', filter_var(parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST), FILTER_VALIDATE_IP) ? 'localhost' : (parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost')),
    'demo_domains' => array_filter(explode(',', env('TENANCY_DEMO_DOMAINS', ''))),
    'bootstrappers' => [],
    'features' => [],
    'database' => ['central_connection' => env('DB_CONNECTION', 'sqlite')],
    'migration_parameters' => [],
    'scoped_tables' => [
        'salons', 'users', 'staff', 'services', 'service_staff', 'guests', 'bookings', 'booking_events', 'time_blocks', 'work_exceptions',
        'outbox_messages', 'sms_outbox_messages', 'google_calendar_connections', 'google_calendar_events', 'google_calendar_busies',
        'waitlist_entries', 'waitlist_offers', 'benefit_settings', 'pass_plans', 'guest_passes', 'pass_uses', 'loyalty_entries',
        'gift_vouchers', 'voucher_redemptions', 'fiscal_documents', 'leave_requests',
    ],
];
