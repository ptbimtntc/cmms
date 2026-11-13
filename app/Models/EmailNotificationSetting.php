<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailNotificationSetting extends Model
{
    protected $fillable = [
        'enabled',
        'sender_email',
        'sender_password',
        'recipients',
        'last_test_at',
        'last_test_ok',
        'last_test_message',
    ];

    /** The App Password must never leave the model via serialization. */
    protected $hidden = ['sender_password'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sender_password' => 'encrypted',
            'recipients' => 'array',
            'last_test_at' => 'datetime',
            'last_test_ok' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self(['enabled' => false, 'recipients' => []]);
    }

    public function hasPassword(): bool
    {
        return ! empty($this->sender_password);
    }

    /**
     * @return array<int, string>
     */
    public function recipientList(): array
    {
        return array_values(array_filter((array) $this->recipients));
    }

    /**
     * Why sending cannot happen right now, or null when fully configured.
     * (Does not look at `enabled`; see disabledReason()/readyReason().)
     */
    public function incompleteReason(): ?string
    {
        if (! filter_var($this->sender_email, FILTER_VALIDATE_EMAIL)
            || ! str_ends_with(strtolower((string) $this->sender_email), '@gmail.com')) {
            return 'The sender Gmail address is missing or not valid.';
        }

        if (! $this->hasPassword()) {
            return 'The Gmail App Password is missing.';
        }

        if ($this->recipientList() === []) {
            return 'The recipient email list is empty.';
        }

        return null;
    }

    /** Null when KRITIS notifications may be sent; otherwise a human reason. */
    public function notReadyReason(): ?string
    {
        if (! $this->enabled) {
            return 'Notifikasi email nonaktif.';
        }

        return $this->incompleteReason();
    }
}
