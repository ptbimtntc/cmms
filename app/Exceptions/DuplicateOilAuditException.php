<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Business rejection: this machine already has an Oil Audit for the
 * business date, regardless of which PIC or device created it.
 */
class DuplicateOilAuditException extends RuntimeException
{
    public static function forMachine(string $machineNumber): self
    {
        return new self("Mesin {$machineNumber} sudah memiliki audit oli untuk hari ini. Satu mesin hanya dapat diaudit satu kali per hari.");
    }
}
