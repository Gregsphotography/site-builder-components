<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Commands;

use Illuminate\Console\Command;
use SiteBuilder\Components\Forms\FormNotification;
use SiteBuilder\Components\Forms\FormRuntime;
use SiteBuilder\Components\Forms\NotificationOutbox;

final class FormsDispatchCommand extends Command
{
    protected $signature = 'site-builder:forms-dispatch {--limit=100}';

    protected $description = 'Enqueue due durable enquiry notifications, including jobs whose dispatch or worker lease expired';

    public function handle(FormRuntime $runtime, NotificationOutbox $outbox): int
    {
        if (! $runtime->available()) {
            $this->components->error('Forms are not enabled and ready.');

            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->components->error('Use a limit between 1 and 1000.');

            return self::INVALID;
        }
        $records = FormNotification::query()->where('status', '!=', 'sent')->where('attempts', '<', 5)->where(fn ($query) => $query->whereNull('retry_at')->orWhere('retry_at', '<=', now()))->where(fn ($query) => $query->whereNull('locked_until')->orWhere('locked_until', '<=', now()))->oldest()->limit($limit)->get();
        $queued = 0;
        foreach ($records as $record) {
            $queued += $outbox->enqueue($record->id) ? 1 : 0;
        }
        $this->components->info($queued.' notification(s) queued. Failed notifications remain visible in the inbox.');

        return self::SUCCESS;
    }
}
