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
        $showHero = (bool) $salon->hero_image || $salon->demo;

        return $appearance + [
            'theme' => $salon->theme,
            'label' => $preset['label'],
            'demo_copy' => $preset['demo_copy'],
            'accent' => $accent,
            'on_accent' => $this->foreground($accent),
            'font' => $salon->font,
            'show_hero' => $showHero,
            'hero' => $salon->hero_image ? Storage::url($salon->hero_image) : ($salon->demo ? asset('assets/branding/'.$preset['image'].'-hero.webp') : null),
            'hero_mobile' => $salon->hero_image ? Storage::url($salon->hero_image) : ($salon->demo ? asset('assets/branding/'.$preset['image'].'-hero-mobile.webp') : null),
            'studio' => $salon->hero_image ? Storage::url($salon->hero_image) : ($salon->demo ? asset('assets/branding/'.$preset['image'].'-studio.webp') : null),
            'custom_hero' => (bool) $salon->hero_image,
            'logo' => $salon->logo ? Storage::url($salon->logo) : asset('assets/branding/sp-mark.png'),
            'custom_logo' => (bool) $salon->logo,
        ];
    }

    /** @return array{salon?: string, barber?: string, nails?: string} */
    public function demoUrls(): array
    {
        $urls = array_filter(array_intersect_key(config('salon.demo_urls'), config('branding.presets')), static fn ($url): bool => is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true));

        return count($urls) === count(array_unique($urls)) ? $urls : [];
    }

    public function demoSalesContactUrl(): ?string
    {
        $contact = config('salon.demo_sales_contact');
        if (! is_string($contact) || $contact === '') {
            return null;
        }
        if (filter_var($contact, FILTER_VALIDATE_EMAIL) !== false) {
            return 'mailto:'.$contact;
        }

        return filter_var($contact, FILTER_VALIDATE_URL) !== false
            && parse_url($contact, PHP_URL_SCHEME) === 'https' ? $contact : null;
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
