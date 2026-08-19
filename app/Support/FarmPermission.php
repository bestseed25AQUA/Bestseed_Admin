<?php

namespace App\Support;

use App\Models\FarmAccessGrant;

/**
 * What a single farmer is allowed to do with a single farm.
 *
 * There are exactly three ways to hold one of these:
 *
 *   owner()      – the farm's `farmer_id`; every ability, always.
 *   fromGrant()  – a manager/partner who scanned a QR and passed the PIN;
 *                  abilities are whatever the grant stored, nothing more.
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
        public readonly bool $create,
        public readonly bool $delete,
        public readonly ?int $grantId = null,
        public readonly ?string $expiresAt = null,
    ) {
    }

    public static function owner(): self
    {
        return new self(self::ROLE_OWNER, true, true, true, true);
    }

    public static function none(): self
    {
        return new self(self::ROLE_NONE, false, false, false, false);
    }

    public static function fromGrant(FarmAccessGrant $grant): self
    {
        return new self(
            role: $grant->role === self::ROLE_PARTNER ? self::ROLE_PARTNER : self::ROLE_MANAGER,
            view: (bool) $grant->view_access,
            edit: (bool) $grant->edit_access,
            create: (bool) $grant->create_access,
            delete: (bool) $grant->delete_access,
            grantId: $grant->id,
            expiresAt: optional($grant->expires_at)->toIso8601String(),
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
            'view'   => $this->view,
            'edit'   => $this->edit,
            'create' => $this->create,
            'delete' => $this->delete,
            default  => false,
        };
    }

    /** Shape returned to the apps alongside each farm. */
    public function toArray(): array
    {
        return [
            'role'        => $this->role,
            'is_owner'    => $this->isOwner(),
            'grant_id'    => $this->grantId,
            'expires_at'  => $this->expiresAt,
            'permissions' => [
                'view'   => $this->view,
                'edit'   => $this->edit,
                'create' => $this->create,
                'delete' => $this->delete,
            ],
        ];
    }
}
