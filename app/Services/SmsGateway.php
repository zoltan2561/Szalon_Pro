<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SmsGateway
{
    public function configured(): bool
    {
        return config('services.sms.provider') === 'twilio'
            && preg_match('/^AC[0-9a-fA-F]{32}$/', (string) config('services.sms.account_sid')) === 1
            && filled(config('services.sms.auth_token'))
            && filled(config('services.sms.sender'));
    }

    public function normalizeNumber(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }
        $number = preg_replace('/[\s().-]+/u', '', trim($phone));
        if (! is_string($number)) {
            return null;
        }
        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        } elseif (str_starts_with($number, '06')) {
            $number = '+36'.substr($number, 2);
        } elseif (preg_match('/^36\d{9}$/', $number)) {
            $number = '+'.$number;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $number) ? $number : null;
    }

    /** @return array{outcome: string, provider_id: ?string, error_code: ?string} */
    public function send(string $recipient, string $body): array
    {
        if (! $this->configured()) {
            return ['outcome' => 'failed', 'provider_id' => null, 'error_code' => 'sms_not_configured'];
        }
        try {
            $response = Http::asForm()->withBasicAuth(config('services.sms.account_sid'), config('services.sms.auth_token'))
                ->acceptJson()->timeout(15)
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.config('services.sms.account_sid').'/Messages.json', [
                    'To' => $recipient,
                    'From' => config('services.sms.sender'),
                    'Body' => $body,
                ]);
        } catch (ConnectionException) {
            return ['outcome' => 'uncertain', 'provider_id' => null, 'error_code' => 'sms_connection_uncertain'];
        }
        if ($response->status() === 201) {
            $providerId = $response->json('sid');
            if (! is_string($providerId) || ! preg_match('/^(SM|MM)[0-9a-fA-F]{32}$/', $providerId)) {
                return ['outcome' => 'uncertain', 'provider_id' => null, 'error_code' => 'sms_invalid_response'];
            }

            return ['outcome' => 'accepted', 'provider_id' => $providerId, 'error_code' => null];
        }
        if ($response->status() === 429) {
            return ['outcome' => 'retry', 'provider_id' => null, 'error_code' => 'sms_rate_limited'];
        }

        return ['outcome' => $response->serverError() ? 'uncertain' : 'failed', 'provider_id' => null, 'error_code' => 'sms_http_'.$response->status()];
    }
}
