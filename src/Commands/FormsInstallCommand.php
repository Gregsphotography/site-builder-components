<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SiteBuilder\Core\Installation\MigrationPublisher;

final class FormsInstallCommand extends Command
{
    protected $signature = 'site-builder:forms-install';

    protected $description = 'Explicitly publish optional enquiry schema and a disabled installation-specific forms configuration';

    public function handle(MigrationPublisher $migrations): int
    {
        $path = config_path('site-builder-forms.php');
        if (! is_file($path)) {
            $configuration = require __DIR__.'/../../config/site-builder-forms.php';
            $configuration['installation_id'] = (string) Str::uuid();
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            $handle = fopen($path, 'x');
            if ($handle === false) {
                $this->components->error('Could not publish the forms configuration without overwriting an existing file.');

                return self::FAILURE;
            }
            try {
                fwrite($handle, "<?php\n\nreturn ".var_export($configuration, true).";\n");
            } finally {
                fclose($handle);
            }
        }
        $published = $migrations->publish(__DIR__.'/../../database/forms-migrations', database_path('migrations'));
        $this->components->info(count($published).' forms migration(s) published. Existing configuration is preserved; forms remain disabled until the host explicitly enables them.');
        $this->components->info('Run migrations, register NotificationProfile/Submission policies, enable forms and configure the asynchronous queue. Schedule site-builder:forms-dispatch every minute and site-builder:forms-prune daily. Run site-builder:forms-doctor to inspect readiness.');

        return self::SUCCESS;
    }
}
