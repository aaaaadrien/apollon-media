<?php
declare(strict_types=1);

namespace Apollon;

final class Pwa
{
    /**
     * Web app manifest payload.
     *
     * @return array<string, mixed>
     */
    public static function manifest(): array
    {
        return [
            'name' => APP_NAME,
            'short_name' => APP_NAME,
            'start_url' => './',
            'display' => 'standalone',
            'background_color' => '#121212',
            'theme_color' => '#121212',
            'description' => Language::t('pwa.description'),
            'icons' => [
                [
                    'src' => '?action=get_app_icon&v=' . APP_VERSION,
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
            ],
        ];
    }

    public static function serviceWorker(): string
    {
        $script = (string)file_get_contents(__DIR__ . '/../assets/sw.js');
        return str_replace('__APP_VERSION__', APP_VERSION, $script);
    }
}
