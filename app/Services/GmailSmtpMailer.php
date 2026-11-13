<?php

namespace App\Services;

use App\Models\EmailNotificationSetting;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Sends through Gmail SMTP (smtp.gmail.com:587, STARTTLS required) using the
 * Admin-managed sender and App Password. Host/port/encryption are fixed on
 * purpose — there is no free SMTP host input.
 */
class GmailSmtpMailer
{
    public const MAILER = 'notification_gmail';

    public const HOST = 'smtp.gmail.com';

    public const PORT = 587;

    /**
     * @param  array<int, string>  $recipients
     */
    public function send(EmailNotificationSetting $setting, array $recipients, Mailable $mailable): void
    {
        $this->mailer($setting)->to($recipients)->send($mailable);
    }

    private function mailer(EmailNotificationSetting $setting): Mailer
    {
        Mail::extend('gmail-starttls', function (array $config) {
            $transport = new EsmtpTransport($config['host'], $config['port'], false);
            $transport->setRequireTls(true);
            $transport->setUsername($config['username']);
            $transport->setPassword($config['password']);
            $transport->getStream()->setTimeout(15);

            return $transport;
        });

        config(['mail.mailers.'.self::MAILER => [
            'transport' => 'gmail-starttls',
            'host' => self::HOST,
            'port' => self::PORT,
            'username' => $setting->sender_email,
            'password' => $setting->sender_password,
        ]]);
        config(['mail.from' => [
            'address' => $setting->sender_email,
            'name' => config('app.name'),
        ]]);

        // Drop any cached instance so a changed sender/password applies immediately.
        Mail::purge(self::MAILER);

        return Mail::mailer(self::MAILER);
    }

    /**
     * Error text safe to store/show: strips the App Password and caps length.
     */
    public static function sanitize(\Throwable $e, EmailNotificationSetting $setting): string
    {
        $message = $e->getMessage();

        foreach (array_filter([$setting->sender_password, (string) $setting->sender_password !== '' ? str_replace(' ', '', (string) $setting->sender_password) : null]) as $secret) {
            $message = str_replace($secret, '[disembunyikan]', $message);
        }

        if (stripos($message, 'Username and Password not accepted') !== false
            || stripos($message, 'Authentication') !== false || str_contains($message, '535')) {
            return 'Gmail rejected the sign-in. Check the Gmail address and App Password (2-step verification must be on).';
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $message)), 0, 300);
    }
}
