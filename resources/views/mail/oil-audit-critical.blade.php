<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:16px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
    <tr><td style="background:#b91c1c;color:#ffffff;padding:16px 20px;font-size:18px;font-weight:bold;">Peringatan Oil Audit KRITIS</td></tr>
    <tr><td style="padding:20px;font-size:14px;line-height:1.5;">
        <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;">
            <tr><td style="color:#6b7280;width:38%;">Machine</td><td><strong>{{ $audit->machine_number }}</strong></td></tr>
            @if ($audit->machine_type)
                <tr><td style="color:#6b7280;">Machine Type</td><td>{{ $audit->machine_type }}</td></tr>
            @endif
            @if ($audit->area)
                <tr><td style="color:#6b7280;">Area</td><td>{{ $audit->area }}</td></tr>
            @endif
            <tr><td style="color:#6b7280;">Tanggal Audit</td><td>{{ $audit->audited_at?->timezone(config('app.timezone'))->format('d-m-Y H:i') }}</td></tr>
            @if ($audit->audited_by_name)
                <tr><td style="color:#6b7280;">Diaudit oleh</td><td>{{ $audit->audited_by_name }}</td></tr>
            @endif
            <tr><td style="color:#6b7280;">Kondisi Oli</td><td style="color:#b91c1c;"><strong>{{ $audit->conditionLabel() }}</strong></td></tr>
        </table>
        <p style="margin:20px 0 0;">
            <a href="{{ $url }}" style="display:inline-block;background:#b91c1c;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:bold;">Lihat Detail Audit</a>
        </p>
        <p style="margin:16px 0 0;font-size:12px;color:#6b7280;">The link needs you to be signed in. This email was sent automatically by {{ config('app.name') }}.</p>
    </td></tr>
</table>
</body>
</html>
