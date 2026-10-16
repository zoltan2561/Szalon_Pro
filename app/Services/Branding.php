<?php

namespace App\Services;

use App\Models\Salon;
use Illuminate\Support\Facades\Storage;

class Branding
{
    public function preset(string $key): array
    {
        return config('branding.presets.'.$key, config('branding.presets.salon'));
    }

    public function forSalon(Salon $salon): array
    {
        $preset = $this->preset($salon->theme);
        $appearance = array_replace($preset['appearance'], $salon->appearance ?? []);
        $accent = $salon->accent ?: $preset['accent'];

        return $appearance + [
            'label' => $preset['label'],
            'accent' => $accent,
            'on_accent' => $this->foreground($accent),
            'font' => $salon->font,
            'hero' => $salon->hero_image ? Storage::url($salon->hero_image) : asset('assets/branding/'.$preset['image'].'-hero.webp'),
            'hero_mobile' => $salon->hero_image ? Storage::url($salon->hero_image) : asset('assets/branding/'.$preset['image'].'-hero-mobile.webp'),
            'custom_hero' => (bool) $salon->hero_image,
            'logo' => $salon->logo ? Storage::url($salon->logo) : asset('assets/branding/sp-mark.png'),
            'custom_logo' => (bool) $salon->logo,
        ];
    }

    public function foreground(string $background): string
    {
        return $this->contrast($background, '#FFFFFF') >= $this->contrast($background, '#171A18') ? '#FFFFFF' : '#171A18';
    }

    public function contrast(string $first, string $second): float
    {
        $a = $this->luminance($first);
        $b = $this->luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    private function luminance(string $hex): float
    {
        $channels = array_map(function (string $channel): float {
            $value = hexdec($channel) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
    }
}
