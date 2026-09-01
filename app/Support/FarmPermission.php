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
