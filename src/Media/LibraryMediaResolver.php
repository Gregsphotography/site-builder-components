<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Media;

use SiteBuilder\Core\Contracts\TypedMediaResolver;
use SiteBuilder\Core\Rendering\PathMediaResolver;

final class LibraryMediaResolver implements TypedMediaResolver
{
    public function __construct(private MediaLibrary $library, private PathMediaResolver $fallback) {}

    public function url(string $path): ?string
    {
        $directory = trim(config('site-builder-components.media.directory', 'site-builder-media'), '/').'/';
        if (str_starts_with(ltrim($path, '/'), $directory)) {
            return $this->library->url(ltrim($path, '/'));
        }

        return $this->fallback->url($path);
    }

    public function kind(string $path): ?string
    {
        $directory = trim(config('site-builder-components.media.directory', 'site-builder-media'), '/').'/';
        if (str_starts_with(ltrim($path, '/'), $directory)) {
            return $this->library->kind(ltrim($path, '/'));
        }

        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION))) {
            'mp4', 'webm' => 'video', 'vtt' => 'track', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg' => 'image', default => null,
        };
    }
}
