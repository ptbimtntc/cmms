<?php

namespace App\Mail;

use App\Models\OilAudit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OilAuditCriticalMail extends Mailable
{
    public function __construct(public OilAudit $oilAudit) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Oil Audit CRITICAL: '.$this->oilAudit->machine_number);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.oil-audit-critical',
            with: [
                'audit' => $this->oilAudit,
                'url' => route('oil-audits.history', $this->oilAudit->machine_number),
            ],
        );
    }
}
