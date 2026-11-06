<?php

declare(strict_types=1);

return [
    'register_standard' => true,
    'register_demo_compatibility' => true,
    'theme' => 'minimal',
    'assets' => true,
    // Trusted host component types that may appear inside standard layout blocks.
    'additional_layout_children' => [],
    'navigation' => ['header' => 'primary', 'footer' => 'footer'],
    // Copy sources are opt-in AND require the host's copySiteBuilderMetadata/Menu gates.
    'snapshots' => ['metadata' => [], 'menus' => []],
    'media' => [
        'disk' => 'public',
        'directory' => 'site-builder-media',
        'max_kilobytes' => 10240,
    ],
];
