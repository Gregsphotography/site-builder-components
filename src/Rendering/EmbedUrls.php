<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

final class EmbedUrls
{
    public function video(string $provider, ?string $url): ?string
    {
        $parts = is_string($url) ? parse_url($url) : false;
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);
        if ($provider === 'youtube' && in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtube-nocookie.com'], true)) {
            $id = $host === 'youtu.be' ? $path : ($path === 'watch' ? ($query['v'] ?? '') : (preg_match('~^(?:embed|shorts)/([A-Za-z0-9_-]{11})$~D', $path, $match) ? $match[1] : ''));

            return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) ? 'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0' : null;
        }
        if ($provider === 'vimeo' && in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true) && preg_match('~^(?:video/)?([0-9]{1,12})$~D', $path, $match)) {
            return 'https://player.vimeo.com/video/'.$match[1];
        }

        return null;
    }

    public function map(?string $url): ?string
    {
        $parts = is_string($url) ? parse_url($url) : false;
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), ['www.google.com', 'maps.google.com'], true) || ($parts['path'] ?? '') !== '/maps/embed' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);

        return is_string($query['pb'] ?? null) && strlen($query['pb']) <= 8192 ? 'https://www.google.com/maps/embed?'.http_build_query(['pb' => $query['pb']]) : null;
    }
}
