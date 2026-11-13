<?php

namespace App\Http\Controllers;

use App\Mail\NotificationTestMail;
use App\Models\EmailNotificationSetting;
use App\Models\OilAuditNotification;
use App\Services\GmailSmtpMailer;
use App\Services\QueueWorkerStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Admin-only (role:ADMIN route group) settings for the Oil Audit KRITIS
 * email notification. The App Password is write-only: it is never passed to
 * the view.
 */
class EmailNotificationSettingController extends Controller
{
    public function edit(): View
    {
        $setting = EmailNotificationSetting::current();

        return view('email-notifications.edit', [
            'setting' => $setting,
            'hasPassword' => $setting->hasPassword(),
            'recipients' => old('recipients', $setting->recipientList() ?: ['']),
            'worker' => QueueWorkerStatus::check(),
            'recent' => OilAuditNotification::with('oilAudit:id,machine_number')->latest('id')->limit(10)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = EmailNotificationSetting::current();

        $request->merge([
            'sender_email' => trim((string) $request->input('sender_email')),
            'app_password' => preg_replace('/\s+/', '', (string) $request->input('app_password')),
            'recipients' => array_map(fn ($r) => trim((string) $r), (array) $request->input('recipients', [])),
        ]);
        $enabled = $request->boolean('enabled');

        $validated = $request->validate([
            'sender_email' => [
                Rule::requiredIf($enabled || $request->input('sender_email') !== ''),
                'nullable', 'email:rfc', 'max:255', 'regex:/^[^@\s]+@gmail\.com$/i',
            ],
            'app_password' => [
                Rule::requiredIf($enabled && ! $setting->hasPassword()),
                'nullable', 'regex:/^[a-zA-Z]{16}$/',
            ],
            'recipients' => ['array', 'max:20'],
            'recipients.*' => ['nullable', 'email:rfc', 'max:255'],
        ], [
            'sender_email.regex' => 'The sender must be an @gmail.com address.',
            'sender_email.required' => 'The sender Gmail address is required.',
            'app_password.regex' => 'The App Password must be 16 letters (spaces are ignored).',
            'app_password.required' => 'The Gmail App Password is required.',
            'recipients.*.email' => 'A recipient email address is not valid.',
        ]);

        $filled = array_values(array_filter($validated['recipients'] ?? [], fn ($r) => $r !== ''));

        if ($enabled && $filled === []) {
            throw ValidationException::withMessages(['recipients' => 'At least one recipient email is required.']);
        }

        if (count($filled) !== count(array_unique(array_map('strtolower', $filled)))) {
            throw ValidationException::withMessages(['recipients' => 'Recipient emails must not repeat.']);
        }

        $senderEmail = ($validated['sender_email'] ?? '') !== '' ? strtolower($validated['sender_email']) : null;
        $newPassword = (string) ($validated['app_password'] ?? '');

        $data = ['enabled' => $enabled, 'sender_email' => $senderEmail, 'recipients' => $filled];

        if ($newPassword !== '') {
            $data['sender_password'] = $newPassword;
        }

        // A changed sender/password invalidates the previous test result.
        if ($setting->exists && ($setting->sender_email !== $senderEmail || $newPassword !== '')) {
            $data += ['last_test_at' => null, 'last_test_ok' => null, 'last_test_message' => null];
        }

        $setting->exists ? $setting->update($data) : $setting->fill($data)->save();

        return redirect()->route('email-notifications.edit')
            ->with('success', 'Email notification settings saved.');
    }

    public function test(): RedirectResponse
    {
        $setting = EmailNotificationSetting::current();

        if (! $setting->exists || $reason = $setting->incompleteReason()) {
            return redirect()->route('email-notifications.edit')
                ->with('error', ($reason ?? 'Pengaturan belum disimpan.').' Simpan pengaturan terlebih dahulu.');
        }

        // Synchronous on purpose: the result shown is the real SMTP outcome,
        // not "queued". No OilAudit / OilAuditNotification is created.
        try {
            app(GmailSmtpMailer::class)->send($setting, $setting->recipientList(), new NotificationTestMail);
            $ok = true;
            $message = 'Test email sent to '.count($setting->recipientList()).' penerima (diterima server Gmail).';
        } catch (Throwable $e) {
            $ok = false;
            $message = 'Could not send: '.GmailSmtpMailer::sanitize($e, $setting);
        }

        $setting->update(['last_test_at' => now(), 'last_test_ok' => $ok, 'last_test_message' => $message]);

        return redirect()->route('email-notifications.edit')->with($ok ? 'success' : 'error', $message);
    }
}
