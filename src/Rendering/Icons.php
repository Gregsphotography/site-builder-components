<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Only audited, bundled SVGs may be rendered; stored values are catalogue keys. */
final class Icons
{
    public const GENERAL = ['check-circle', 'star', 'heart', 'light-bulb', 'phone', 'envelope', 'map-pin', 'clock', 'user-group', 'shield-check', 'chart-bar', 'sparkles', 'arrow-right', 'globe-alt', 'academic-cap', 'camera'];

    public const SOCIAL = ['facebook', 'instagram', 'youtube', 'github', 'whatsapp', 'mastodon', 'bluesky', 'pinterest'];

    public static function options(): array
    {
        return array_combine(self::GENERAL, array_map(Str::headline(...), self::GENERAL));
    }

    public function html(?string $name, bool $social = false): HtmlString
    {
        if (! in_array($name, $social ? self::SOCIAL : self::GENERAL, true)) {
            return new HtmlString('');
        }
        $path = __DIR__.'/../../resources/icons/'.($social ? 'social-' : '').$name.'.svg';
        $svg = is_file($path) ? file_get_contents($path) : '';

        return new HtmlString(preg_replace('/<svg\b/', '<svg aria-hidden="true" focusable="false"', $svg, 1));
    }
}
