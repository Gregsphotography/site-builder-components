<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Commands;

use Illuminate\Console\Command;
use SiteBuilder\Components\Media\MediaLibrary;

final class MediaDoctorCommand extends Command
{
    protected $signature = 'site-builder:media-doctor {--json}';

    protected $description = 'Inspect image/video/caption upload limits without changing host configuration';

    public function handle(MediaLibrary $media): int
    {
        $limits = [];
        foreach (['image', 'video', 'track'] as $kind) {
            $limits[$kind] = $media->uploadReadiness($kind);
        }
        $ready = $media->available() && array_all($limits, fn ($limit): bool => $limit['effective_kilobytes'] >= $limit['configured_kilobytes']);
        $this->line(json_encode(['ready' => $ready, 'storage' => $media->available(), 'limits' => $limits, 'action' => 'Host PHP, web server and temporary upload limits must support the chosen sizes. No automatic encoding or thumbnail extraction is performed.'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
