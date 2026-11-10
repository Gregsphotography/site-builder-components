<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SiteBuilder\Components\Forms\FormNotification;
use SiteBuilder\Components\Forms\FormRuntime;
use SiteBuilder\Components\Forms\Submission;

final class FormsPruneCommand extends Command
{
    protected $signature = 'site-builder:forms-prune {--dry-run}';

    protected $description = 'Remove enquiries older than the explicitly configured retention period';

    public function handle(FormRuntime $runtime): int
    {
        if (! $runtime->available()) {
            return self::FAILURE;
        }
        $days = config('site-builder-forms.retention_days', 90);
        if ($days === null) {
            $this->components->info('Automatic retention is disabled.');

            return self::SUCCESS;
        }
        if (! is_int($days) || $days < 1) {
            $this->components->error('Retention must be a positive number of days or null.');

            return self::INVALID;
        }
        $count = 0;
        do {
            $ids = Submission::query()->where('created_at', '<', now()->subDays($days))->orderBy('id')->limit(200)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $count += $ids->count();
            if ($this->option('dry-run')) {
                $count = Submission::query()->where('created_at', '<', now()->subDays($days))->count();
                break;
            }
            DB::transaction(function () use ($ids): void {
                FormNotification::query()->whereIn('submission_id', $ids)->delete();
                Submission::query()->whereIn('id', $ids)->delete();
            });
        } while ($ids->count() === 200);
        $this->components->info($count.' enquiry/notification pair(s) '.($this->option('dry-run') ? 'would be removed.' : 'removed.'));

        return self::SUCCESS;
    }
}
