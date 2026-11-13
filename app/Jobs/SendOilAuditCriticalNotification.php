<?php

namespace App\Jobs;

use App\Mail\OilAuditCriticalMail;
use App\Models\EmailNotificationSetting;
use App\Models\OilAuditNotification;
use App\Services\GmailSmtpMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends the KRITIS email for one OilAuditNotification row. The row is claimed
 * with a conditional UPDATE so two workers / a duplicate dispatch can never
 * both send, and a row already `sent` is never re-sent on retry.
 */
class SendOilAuditCriticalNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** A `sending` claim older than this is considered abandoned (worker died). */
    private const STALE_CLAIM_MINUTES = 10;

    public function __construct(public int $notificationId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(GmailSmtpMailer $gmail): void
    {
        $claimed = OilAuditNotification::query()
            ->whereKey($this->notificationId)
            ->where(function ($q) {
                $q->whereIn('status', [OilAuditNotification::PENDING, OilAuditNotification::FAILED])
                    ->orWhere(function ($q) {
                        $q->where('status', OilAuditNotification::SENDING)
                            ->where('updated_at', '<', now()->subMinutes(self::STALE_CLAIM_MINUTES));
                    });
            })
            ->update([
                'status' => OilAuditNotification::SENDING,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return; // already sent / skipped / being sent elsewhere
        }

        $notification = OilAuditNotification::with('oilAudit')->findOrFail($this->notificationId);
        $setting = EmailNotificationSetting::current();

        // Re-check at send time: the Admin may have disabled it since queuing.
        if ($reason = $setting->notReadyReason()) {
            $notification->update(['status' => OilAuditNotification::SKIPPED, 'last_error' => $reason]);

            return;
        }

        if (! $notification->oilAudit) {
            $notification->update(['status' => OilAuditNotification::SKIPPED, 'last_error' => 'Audit data not found.']);

            return;
        }

        try {
            $gmail->send($setting, $setting->recipientList(), new OilAuditCriticalMail($notification->oilAudit));
        } catch (Throwable $e) {
            $notification->update([
                'status' => OilAuditNotification::FAILED,
                'last_error' => GmailSmtpMailer::sanitize($e, $setting),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 300);

                return;
            }

            return; // final failure is recorded on the row; audit data is untouched
        }

        $notification->update([
            'status' => OilAuditNotification::SENT,
            'last_error' => null,
            'sent_at' => now(),
        ]);
    }
}
