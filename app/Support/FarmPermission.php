<?php

namespace App\Support;

use App\Models\FarmAccessMember;

/**
 * What a single farmer is allowed to do with a single farm.
 *
 * There are exactly three ways to hold one of these:
 *
 *   owner()      – the farm's `farmer_id`; every ability, always.
 *   fromMember() – a manager/partner the owner (or another member) gave access
 *                  to; abilities are whatever the membership row stored.
 *   none()       – everyone else; every ability denied.
 *
 * There is deliberately no "default allow" constructor, so a code path that
 * forgets to resolve a permission fails closed rather than open.
 */
final class FarmPermission
{
    public const ROLE_OWNER   = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_PARTNER = 'partner';
    public const ROLE_NONE    = 'none';

    private function __construct(
        public readonly string $role,
        public readonly bool $view,
        public readonly bool $edit,
        public readonly bool $tankStatus,
        public readonly bool $totalFeed,
        public readonly bool $create,
        public readonly bool $delete,
    ) {
    }

    public static function owner(): self
    {
        return new self(self::ROLE_OWNER, true, true, true, true, true, true);
    }

    public static function none(): self
    {
        return new self(self::ROLE_NONE, false, false, false, false, false, false);
    }

    /** Built from a membership row — the only way access is held. */
    public static function fromMember(FarmAccessMember $member): self
    {
        return new self(
            role: $member->role === self::ROLE_PARTNER ? self::ROLE_PARTNER : self::ROLE_MANAGER,
            view: (bool) $member->view_access,
            edit: (bool) $member->edit_access,
            tankStatus: (bool) $member->tank_status_access,
            totalFeed: (bool) $member->total_feed_access,
            create: (bool) $member->create_access,
            delete: (bool) $member->delete_access,
        );
    }

    /** True when the farmer owns the farm outright. */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /** True when the farmer has no relationship to the farm at all. */
    public function isDenied(): bool
    {
        return $this->role === self::ROLE_NONE;
    }

    public function isPartner(): bool
    {
        return $this->role === self::ROLE_PARTNER;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /**
     * Whether this person may hand the farm to somebody else.
     *
     * Owners and partners only. A partner is a co-owner and may bring people
     * in; a manager is staff, and staff do not widen access. This used to be
     * "anyone holding any permission", which let a manager given view access
     * appoint managers and partners of their own.
     *
     * Holding something to give is still required — a grant of nothing is
     * refused either way, and this keeps the reason honest.
     */
    public function canShareAccess(): bool
    {
        // CREATE is the ability that decides it, for a manager and a partner
        // alike. Bringing someone onto the farm is creating something, so the
        // checkbox the owner already ticks for "may add things" governs it —
        // rather than the role, which said a partner could always share and a
        // manager never could, whatever either had been given.
        return $this->isOwner() || $this->create;
    }

    /**
     * Whether they may TAKE access away.
     *
     * Create AND delete. Delete alone is not enough: without create they
     * cannot reach the access screen at all, so a grant of delete on its own
     * would be a permission with nowhere to be used. Delete is what turns
     * "may bring people in" into "may also remove them".
     */
    public function canRevokeAccess(): bool
    {
        return $this->isOwner() || ($this->create && $this->delete);
    }

    /**
     * Check one ability. An unknown ability name is denied rather than
     * silently allowed, so a typo in a route definition cannot open a hole.
     */
    public function allows(string $ability): bool
    {
        return match ($ability) {
            'view'        => $this->view,
            'edit'        => $this->edit,
            'tank_status' => $this->tankStatus,
            'total_feed'  => $this->totalFeed,
            'create'      => $this->create,
            'delete'      => $this->delete,
            default       => false,
        };
    }

    /** Shape returned to the apps alongside each farm. */
    public function toArray(): array
    {
        return [
            'role'        => $this->role,
            'is_owner'    => $this->isOwner(),
            // Sent rather than left for the app to re-derive, so the option it
            // offers and the rule the server enforces cannot drift apart.
            'can_share_access'  => $this->canShareAccess(),
            'can_revoke_access' => $this->canRevokeAccess(),
            'permissions' => [
                'view'        => $this->view,
                'edit'        => $this->edit,
                'tank_status' => $this->tankStatus,
                'total_feed'  => $this->totalFeed,
                'create'      => $this->create,
                'delete'      => $this->delete,
            ],
        ];
    }
}
