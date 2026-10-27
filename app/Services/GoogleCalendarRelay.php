<?php

namespace App\Services;

use RuntimeException;

class GoogleCalendarRelay
{
    public function state(string $nonce): string
    {
        $callback = config('services.google_calendar.relay_callback');
        $key = config('services.google_calendar.relay_key');
        if (blank($callback) && blank($key)) {
            if (str_ends_with((string) config('services.google_calendar.redirect_uri'), '/google-naptar/kozponti-visszateres')) {
                throw new RuntimeException('google_relay_not_configured');
            }

            return $nonce;
        }
        if (! $this->validCallback($callback) || ! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('google_relay_not_configured');
        }
        $payload = rtrim(strtr(base64_encode(json_encode(['callback' => $callback, 'nonce' => $nonce, 'expires' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $payload.'.'.hash_hmac('sha256', $payload, $key);
    }

    public function callback(string $state): ?string
    {
        if (strlen($state) > 4096 || ! preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/D', $state, $parts)) {
            return null;
        }
        $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        $clients = json_decode((string) config('services.google_calendar.relay_clients', '{}'), true);
        if (! is_array($payload) || ! is_array($clients) || ! $this->validCallback($payload['callback'] ?? null)
            || ! is_string($payload['nonce'] ?? null) || strlen($payload['nonce']) !== 64
            || ! is_int($payload['expires'] ?? null) || $payload['expires'] < now()->timestamp || $payload['expires'] > now()->addMinutes(10)->timestamp) {
            return null;
        }
        $key = $clients[$payload['callback']] ?? null;
        if (! is_string($key) || strlen($key) < 32 || ! hash_equals(hash_hmac('sha256', $parts[1], $key), $parts[2])) {
            return null;
        }

        return $payload['callback'];
    }

    private function validCallback(mixed $callback): bool
    {
        if (! is_string($callback) || strlen($callback) > 2048) {
            return false;
        }
        $parts = parse_url($callback);

        return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && isset($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment'])
            && ($parts['path'] ?? '') === '/admin/google-naptar/visszateres';
    }
}
