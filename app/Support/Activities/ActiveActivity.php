<?php

namespace App\Support\Activities;

use App\Services\ActiveActivityResolver;
use Carbon\CarbonInterface;

/**
 * A single "active activity" a PIC currently owns, normalised across every
 * activity source (PM, Greasing, Oil Audit, Oil Audit Action, and — once it
 * exists — Manual Activity). It is a read-only view derived from existing
 * records/columns: there is no activity table and this object is never
 * persisted.
 *
 * @see ActiveActivityResolver
 */
final class ActiveActivity
{
    public function __construct(
        /** Machine-readable source key: PM | GREASING | OIL_AUDIT | OIL_AUDIT_ACTION | MANUAL. */
        public readonly string $source,
        /** Human label shown in the confirmation modal, e.g. "PM", "Greasing". */
        public readonly string $label,
        /** The PIC (by name) who owns this activity. */
        public readonly string $picName,
        /** When the activity was started. */
        public readonly CarbonInterface $startedAt,
        /**
         * Free-text name of the activity when the source has one (Manual
         * Activity). Shown instead of the generic $label on the monitor and
         * on Today's Activity. Null for the fixed-name sources.
         */
        public readonly ?string $title = null,
        /** Machine number if the activity is tied to one, otherwise null. */
        public readonly ?string $machineNumber = null,
        /** Source record id when the activity lives on its own row (PM/Greasing); null for user-level markers. */
        public readonly ?int $recordId = null,
        /**
         * Group name for group-level sources (Greasing is group-level, not
         * machine-level). Shown on the card as "Group: <name>" when there is
         * no machine.
         */
        public readonly ?string $groupName = null,
        /**
         * When the activity was explicitly finished (Manual Activity only,
         * via the control panel). A finished activity still appears in the
         * panel's "Started Today" list but is never the current/active one.
         */
        public readonly ?CarbonInterface $endedAt = null,
    ) {}

    /**
     * Explicitly finished via the control panel's "Finish" action.
     *
     * Comparing endedAt against startedAt to detect a "stale" closure from a
     * previous instance was tried and reverted: user-entered start times are
     * only minute-precision (HTML datetime-local) while closed_at is
     * second-precision now(), so the two routinely tie or even cross in
     * either direction — no comparison operator (>, >=) is reliable. The
     * real fix is at the source: OilAuditController deletes the PIC's
     * previous Oil Audit / Oil Audit Action closure the moment they restart
     * that source, so a stale closure never lingers to be compared against
     * in the first place. See OilAuditController::currentActivitySource()
     * callers.
     */
    public function isFinished(): bool
    {
        return $this->endedAt !== null;
    }

    /** Same underlying record as another resolved activity. */
    public function sameAs(?self $other): bool
    {
        return $other !== null
            && $other->source === $this->source
            && $other->recordId === $this->recordId;
    }

    /**
     * What to show as the activity name: the free-text title when the
     * source carries one (Manual Activity), otherwise the generic label.
     */
    public function displayLabel(): string
    {
        return $this->title !== null && $this->title !== ''
            ? $this->title
            : $this->label;
    }

    /**
     * The location line for a card: the machine when the source is
     * machine-level, otherwise the group for a group-level source, otherwise
     * nothing.
     */
    public function locationLabel(): ?string
    {
        if ($this->machineNumber) {
            return $this->machineNumber;
        }

        return $this->groupName ? 'Group: '.$this->groupName : null;
    }
}
