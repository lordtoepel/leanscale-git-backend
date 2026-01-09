<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * LeanScale Platform Roles
 *
 * Role hierarchy:
 * - Owner: Full access including billing and organization deletion
 * - Admin: Full access except billing
 * - GTMArchitect: Project manager level - manage projects, tickets, view all time, assign work
 * - GTMEngineer: Can track time, view assigned tickets, complete work
 * - Client: External client access - view their projects, create/comment on tickets
 * - Placeholder: For data import only
 */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case GTMArchitect = 'gtm_architect';
    case GTMEngineer = 'gtm_engineer';
    case Client = 'client';
    case Placeholder = 'placeholder';

    /**
     * Get human-readable label for the role
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Administrator',
            self::GTMArchitect => 'GTM Architect',
            self::GTMEngineer => 'GTM Engineer',
            self::Client => 'Client',
            self::Placeholder => 'Placeholder',
        };
    }

    /**
     * Check if this is a LeanScale internal role (not client)
     */
    public function isInternal(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::GTMArchitect, self::GTMEngineer => true,
            self::Client, self::Placeholder => false,
        };
    }

    /**
     * Check if this role can see billing rates
     */
    public function canSeeBillingRates(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            default => false,
        };
    }
}
