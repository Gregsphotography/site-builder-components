<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use SiteBuilder\Components\Forms\FormNotification;
use SiteBuilder\Components\Forms\FormRuntime;
use SiteBuilder\Components\Forms\NotificationProfile;
use SiteBuilder\Components\Forms\Submission;

final class FormsDoctorCommand extends Command
{
    protected $signature = 'site-builder:forms-doctor {--json}';

    protected $description = 'Inspect optional enquiry readiness without changing configuration, schema or content';

    public function handle(FormRuntime $runtime): int
    {
        $queue = config('site-builder-forms.queue_connection');
        $driver = is_string($queue) ? config('queue.connections.'.$queue.'.driver') : null;
        $ready = $runtime->available();
        $checks = ['enabled' => (bool) config('site-builder-forms.enabled'), 'installation' => $runtime->installation() !== null, 'schema' => $ready, 'profile_policy' => Gate::getPolicyFor(NotificationProfile::class) !== null, 'inbox_policy' => Gate::getPolicyFor(Submission::class) !== null, 'asynchronous_queue' => is_string($driver) && $driver !== 'sync', 'notifications_enabled' => (bool) config('site-builder-forms.notifications_enabled')];
        $status = array_all(array_intersect_key($checks, array_flip(['enabled', 'installation', 'schema', 'profile_policy', 'inbox_policy', 'asynchronous_queue'])), fn ($value): bool => $value);
        $result = ['ready' => $status, 'checks' => $checks, 'failed_notifications' => $ready ? FormNotification::query()->where('status', 'failed')->count() : null, 'worker' => 'Configure a worker for the selected connection and site-builder-forms queue; readiness does not prove that a worker is running.', 'scheduler' => 'forms-dispatch every minute; forms-prune daily.'];
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $status ? self::SUCCESS : self::FAILURE;
    }
}
