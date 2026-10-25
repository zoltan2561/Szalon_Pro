<?php

namespace App\Services;

use App\Models\Salon;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class BookingLinks
{
    public const Sources = ['google', 'facebook', 'instagram', 'qr', 'sms'];

    public function url(string $source, ?int $serviceId = null, ?int $staffId = null): string
    {
        return route('booking.form', array_filter(['utm_source' => $source, 'service_id' => $serviceId, 'staff_id' => $staffId]));
    }

    public function qr(string $url): string
    {
        $accent = app(Branding::class)->forSalon(Salon::current())['accent'];
        $color = app(Branding::class)->contrast($accent, '#ffffff') >= 4.5 ? $accent : '#243b31';
        $rgb = sscanf($color, '#%02x%02x%02x');
        $style = new RendererStyle(400, 4, fill: Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(...$rgb)));

        return (new Writer(new ImageRenderer($style, new SvgImageBackEnd)))->writeString($url);
    }
}
