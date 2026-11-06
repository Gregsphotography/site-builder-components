<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Media;

use SiteBuilder\Core\Contracts\MediaResolver;
use SiteBuilder\Core\Rendering\PathMediaResolver;

final class LibraryMediaResolver implements MediaResolver
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
}
