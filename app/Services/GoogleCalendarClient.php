<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GoogleCalendarConnection;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleCalendarClient
{
    public const Scope = 'https://www.googleapis.com/auth/calendar.events.owned';

    public const CalendarIdentityScope = 'https://www.googleapis.com/auth/calendar.calendarlist.readonly';

    public function configured(): bool
    {
        $redirectUri = config('services.google_calendar.redirect_uri');

        return filled(config('services.google_calendar.client_id'))
            && filled(config('services.google_calendar.client_secret'))
            && is_string($redirectUri)
            && $redirectUri !== ''
            && (! app()->environment('production') || str_starts_with($redirectUri, 'https://'));
    }

    public function redirectUri(): string
    {
        $uri = config('services.google_calendar.redirect_uri');
        if (! is_string($uri) || $uri === '') {
            throw new RuntimeException('google_not_configured');
        }
        if (app()->environment('production') && ! str_starts_with($uri, 'https://')) {
            throw new RuntimeException('google_insecure_redirect');
        }

        return $uri;
    }

    public function authorizationUrl(string $state): string
    {
        $this->requireConfiguration();

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::Scope.' '.self::CalendarIdentityScope,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int} */
    public function exchangeCode(string $code): array
    {
        $this->requireConfiguration();
        $response = Http::asForm()->acceptJson()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);
        $this->requireSuccess($response);
        $token = $response->json();
        $scopes = explode(' ', (string) ($token['scope'] ?? ''));
        if (! is_array($token) || ! is_string($token['access_token'] ?? null) || ! is_string($token['refresh_token'] ?? null) || ! is_numeric($token['expires_in'] ?? null)
            || ! in_array(self::Scope, $scopes, true) || ! in_array(self::CalendarIdentityScope, $scopes, true)) {
            throw new RuntimeException('google_invalid_token');
        }

        return ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_in' => (int) $token['expires_in']];
    }

    /** @return array{id: string, timeZone: string} */
    public function primaryCalendar(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->acceptJson()->timeout(15)->get('https://www.googleapis.com/calendar/v3/users/me/calendarList/primary', ['fields' => 'id,timeZone']);
        $this->requireSuccess($response);
        $id = $response->json('id');
        $timeZone = $response->json('timeZone');
        if (! is_string($id) || $id === '' || strlen($id) > 255 || ! is_string($timeZone) || $timeZone === '') {
            throw new RuntimeException('google_invalid_calendar');
        }

        return ['id' => $id, 'timeZone' => $timeZone];
    }

    public function accessToken(GoogleCalendarConnection $connection, bool $forceRefresh = false): string
    {
        if (! $forceRefresh && $connection->token_expires_at->gt(now()->addMinute())) {
            return $connection->access_token;
        }
        $this->requireConfiguration();
        $response = Http::asForm()->acceptJson()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        if ($response->status() === 400 && $response->json('error') === 'invalid_grant') {
            throw new RuntimeException('google_reconnect_required');
        }
        $this->requireSuccess($response);
        $token = $response->json();
        if (! is_array($token) || ! is_string($token['access_token'] ?? null) || ! is_numeric($token['expires_in'] ?? null)) {
            throw new RuntimeException('google_invalid_token');
        }
        $connection->update(['access_token' => $token['access_token'], 'token_expires_at' => now()->addSeconds((int) $token['expires_in'])]);

        return $token['access_token'];
    }

    /** @return array<int, array<string, mixed>> */
    public function listEvents(GoogleCalendarConnection $connection, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $events = [];
        $pageToken = null;
        do {
            $query = [
                'timeMin' => $from->utc()->toRfc3339String(),
                'timeMax' => $until->utc()->toRfc3339String(),
                'singleEvents' => 'true',
                'showDeleted' => 'false',
                'maxResults' => 2500,
                'fields' => 'nextPageToken,items(id,status,transparency,start,end)',
            ];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }
            $response = $this->request($connection, 'GET', $this->eventsUrl($connection), $query);
            $this->requireSuccess($response);
            $body = $response->json();
            if (! is_array($body) || ! is_array($body['items'] ?? null)) {
                throw new RuntimeException('google_invalid_events');
            }
            $events = array_merge($events, $body['items']);
            $pageToken = $body['nextPageToken'] ?? null;
            if (count($events) > 50000 || ($pageToken !== null && ! is_string($pageToken))) {
                throw new RuntimeException('google_event_limit');
            }
        } while ($pageToken !== null);

        return $events;
    }

    public function eventId(GoogleCalendarConnection $connection, Booking $booking): string
    {
        return 'sp'.substr(hash('sha256', config('app.key').':'.$connection->id.':'.$booking->id), 0, 62);
    }

    public function saveBooking(GoogleCalendarConnection $connection, Booking $booking, string $eventId, bool $insert): void
    {
        $body = [
            'id' => $eventId,
            'summary' => 'SzalonPRO · Foglalt időpont',
            'description' => 'A vendég időpontja: '.$booking->starts_at->setTimezone(Salon::current()->timezone)->format('Y. m. d. H:i').'–'.$booking->ends_at->setTimezone(Salon::current()->timezone)->format('H:i').'. Az esemény a foglalási puffert is tartalmazza.',
            'visibility' => 'private',
            'transparency' => 'opaque',
            'start' => ['dateTime' => $booking->busy_from->utc()->toRfc3339String()],
            'end' => ['dateTime' => $booking->busy_until->utc()->toRfc3339String()],
            'extendedProperties' => ['private' => ['szalonpro' => $this->marker($connection, $booking)]],
        ];
        $url = $this->eventsUrl($connection);
        $eventUrl = $url.'/'.rawurlencode($eventId);
        $response = $this->request($connection, $insert ? 'POST' : 'PUT', $insert ? $url : $eventUrl, $body);
        if ($response->status() === 404 && ! $insert) {
            $response = $this->request($connection, 'POST', $url, $body);
        }
        if ($response->status() === 409) {
            $existing = $this->request($connection, 'GET', $eventUrl);
            $this->requireSuccess($existing);
            if ($existing->json('extendedProperties.private.szalonpro') !== $this->marker($connection, $booking)) {
                throw new RuntimeException('google_event_collision');
            }
            $response = $this->request($connection, 'PUT', $eventUrl, $body);
        }
        $this->requireSuccess($response);
    }

    public function deleteBooking(GoogleCalendarConnection $connection, string $eventId): void
    {
        $response = $this->request($connection, 'DELETE', $this->eventsUrl($connection).'/'.rawurlencode($eventId));
        if (! in_array($response->status(), [404, 410], true)) {
            $this->requireSuccess($response);
        }
    }

    private function eventsUrl(GoogleCalendarConnection $connection): string
    {
        return 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($connection->calendar_id).'/events';
    }

    /** @param array<string, mixed> $payload */
    private function request(GoogleCalendarConnection $connection, string $method, string $url, array $payload = []): Response
    {
        $send = static function (string $accessToken) use ($method, $url, $payload): Response {
            $request = Http::withToken($accessToken)->acceptJson()->timeout(20);

            return match ($method) {
                'GET' => $request->get($url, $payload),
                'POST' => $request->post($url, $payload),
                'PUT' => $request->put($url, $payload),
                'DELETE' => $request->delete($url),
            };
        };
        $accessToken = $this->accessToken($connection);
        $refreshed = false;
        $transientRetries = 0;
        while (true) {
            $response = $send($accessToken);
            if ($response->status() === 401 && ! $refreshed) {
                $accessToken = $this->accessToken($connection, true);
                $refreshed = true;

                continue;
            }
            if (($this->rateLimited($response) || $response->serverError()) && $transientRetries < 2) {
                $retryAfter = $response->header('Retry-After');
                $delay = is_string($retryAfter) && ctype_digit($retryAfter) && (int) $retryAfter <= 2
                    ? (int) $retryAfter * 1_000_000
                    : (150_000 * (2 ** $transientRetries)) + random_int(0, 50_000);
                usleep($delay);
                $transientRetries++;

                continue;
            }

            return $response;
        }
    }

    private function rateLimited(Response $response): bool
    {
        if ($response->status() === 429) {
            return true;
        }
        if ($response->status() !== 403) {
            return false;
        }
        $errors = $response->json('error.errors');
        if (! is_array($errors)) {
            return false;
        }
        foreach ($errors as $error) {
            if (is_array($error) && in_array($error['reason'] ?? null, ['rateLimitExceeded', 'userRateLimitExceeded'], true)) {
                return true;
            }
        }

        return false;
    }

    private function marker(GoogleCalendarConnection $connection, Booking $booking): string
    {
        return hash_hmac('sha256', $connection->id.':'.$booking->id, config('app.key'));
    }

    private function requireConfiguration(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('google_not_configured');
        }
    }

    private function requireSuccess(Response $response): void
    {
        if ($response->successful()) {
            return;
        }
        if ($response->status() === 401) {
            throw new RuntimeException('google_reconnect_required');
        }

        throw new RuntimeException('google_http_'.$response->status());
    }
}
