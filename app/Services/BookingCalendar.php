<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;

class BookingCalendar
{
    public function content(Booking $booking): string
    {
        return $this->contentMany([$booking]);
    }

    /** @param iterable<Booking> $bookings */
    public function contentMany(iterable $bookings): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//SzalonPRO//Idopont//HU', 'CALSCALE:GREGORIAN'];
        foreach ($bookings as $booking) {
            $lines = [...$lines, ...$this->eventLines($booking)];
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /** @return list<string> */
    private function eventLines(Booking $booking): array
    {
        $salon = Salon::current();

        return [
            'BEGIN:VEVENT',
            'UID:'.hash_hmac('sha256', 'booking:'.$booking->id, config('app.key')).'@szalonpro',
            'DTSTAMP:'.$booking->updated_at->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$booking->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'SEQUENCE:'.$booking->revision,
            'SUMMARY:'.$this->escape($booking->service_name.' · '.$salon->name),
            'LOCATION:'.$this->escape($salon->address),
            'DESCRIPTION:'.$this->escape('Szakember: '.$booking->staff->name."\nIdőtartam: ".$booking->duration_minutes.' perc'),
            'STATUS:'.(in_array($booking->status, ['cancelled', 'no_show'], true) ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
        ];
    }

    public function googleUrl(Booking $booking): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE', 'text' => $booking->service_name.' · '.Salon::current()->name,
            'dates' => $booking->starts_at->utc()->format('Ymd\THis\Z').'/'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'location' => Salon::current()->address,
            'details' => 'Szakember: '.$booking->staff->name.' · '.$booking->duration_minutes.' perc',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
    }

    private function fold(string $line): string
    {
        $parts = [];
        $limit = 75;
        while (strlen($line) > $limit) {
            $part = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $part;
            $line = substr($line, strlen($part));
            $limit = 74;
        }
        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}
