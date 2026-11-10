<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** The durable outbox owns retry state; duplicate jobs compete for the same row. */
final class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $notificationId) {}

    public function handle(): void
    {
        if (! app(FormRuntime::class)->available() || ! config('site-builder-forms.notifications_enabled', true)) {
            return;
        }
        $notification = DB::transaction(function (): ?FormNotification {
            $record = FormNotification::query()->lockForUpdate()->find($this->notificationId);
            if ($record === null || $record->status === 'sent' || ($record->status === 'processing' && $record->locked_until?->isFuture())) {
                return null;
            }
            $record->forceFill(['status' => 'processing', 'locked_until' => now()->addMinutes(10), 'attempts' => $record->attempts + 1])->save();

            return $record;
        });
        if ($notification === null) {
            return;
        }
        try {
            $submission = Submission::query()->find($notification->submission_id);
            if ($submission === null) {
                $notification->delete();

                return;
            }
            Mail::to($notification->recipients)->send(new EnquiryMail($submission));
            $notification->forceFill(['status' => 'sent', 'sent_at' => now(), 'locked_until' => null, 'retry_at' => null, 'last_error' => null])->save();
        } catch (\Throwable $exception) {
            $notification->forceFill(['status' => 'failed', 'locked_until' => null, 'retry_at' => now()->addMinutes(min(60, 2 ** min(6, $notification->attempts))), 'last_error' => 'Mail transport failed ('.class_basename($exception).'). The enquiry remains in the inbox.'])->save();
        }
    }
}
