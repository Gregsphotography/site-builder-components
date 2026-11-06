<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Commands;

use Illuminate\Console\Command;
use SiteBuilder\Core\Installation\MigrationPublisher;

final class InstallCommand extends Command
{
    protected $signature = 'site-builder:components-install {--media : Publish the optional local media ownership migration}';

    protected $description = 'Publish standard component configuration and compiled assets without changing existing content';

    public function handle(MigrationPublisher $migrations): int
    {
        foreach (['site-builder-components-config', 'site-builder-components-assets'] as $tag) {
            if ($this->call('vendor:publish', ['--tag' => $tag, '--force' => $tag === 'site-builder-components-assets']) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }
        if ($this->option('media')) {
            $published = $migrations->publish(__DIR__.'/../../database/migrations', database_path('migrations'));
            $this->components->info(count($published).' optional media migration(s) published. Run migrate and storage:link explicitly.');
        }
        $this->components->info('Standard component assets/config published. Choose components.default or a fixed components preset layout in the host. No content was seeded.');

        return self::SUCCESS;
    }
}
