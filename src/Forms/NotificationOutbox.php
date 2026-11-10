<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class NotificationOutbox
{
    public function enqueue(string $id): bool
    {
        $record = DB::transaction(function () use ($id): ?FormNotification {
            $record = FormNotification::query()->lockForUpdate()->find($id);
            if ($record === null || $record->status === 'sent' || (in_array($record->status, ['queued', 'processing'], true) && $record->locked_until?->isFuture()) || $record->attempts >= 5 || $record->retry_at?->isFuture()) {
                return null;
            }
            $record->forceFill(['status' => 'queued', 'locked_until' => now()->addMinutes(10)])->save();

            return $record;
        });
        if ($record === null) {
            return false;
        }
        try {
            $connection = config('site-builder-forms.queue_connection', 'database');
            if (! is_string($connection) || ! is_string(config('queue.connections.'.$connection.'.driver')) || config('queue.connections.'.$connection.'.driver') === 'sync') {
                throw new \LogicException('Configure a durable asynchronous queue connection.');
            }
            $job = (new DeliverNotification($id))->onConnection($connection)->onQueue(config('site-builder-forms.queue', 'site-builder-forms'))->afterCommit();
            Bus::dispatch($job);

            return true;
        } catch (\Throwable) {
            $record->forceFill(['status' => 'failed', 'locked_until' => null, 'retry_at' => now()->addMinute(), 'last_error' => 'Queue dispatch failed. The enquiry remains in the inbox; configure the worker and retry.'])->save();

            return false;
        }
    }

    public function retry(Authenticatable $actor, string $submissionId): bool
    {
        $submission = Submission::query()->findOrFail($submissionId);
        Gate::forUser($actor)->authorize('retry', $submission);
        $record = DB::transaction(function () use ($submissionId): FormNotification {
            $record = FormNotification::query()->where('submission_id', $submissionId)->lockForUpdate()->firstOrFail();
            abort_if($record->status === 'sent' || (in_array($record->status, ['queued', 'processing'], true) && $record->locked_until?->isFuture()), 409, 'This notification is already sent or in progress.');
            $record->forceFill(['status' => 'pending', 'attempts' => 0, 'retry_at' => null, 'locked_until' => null])->save();

            return $record;
        });

        return $this->enqueue($record->id);
    }
}
