<?php

declare(strict_types=1);

namespace App\Models\GitHub;

use Illuminate\Support\Collection;

/**
 * GitHub-backed ProjectMember model (pivot for project-user relationships).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $project_id
 * @property string $user_id
 * @property int|null $billable_rate
 * @property string $created_at
 * @property string $updated_at
 */
class GitHubProjectMember extends GitHubModel
{
    protected static string $entityType = 'project_members';
    protected static bool $organizationScoped = true;

    /**
     * Get the project
     */
    public function project(): ?GitHubProject
    {
        return GitHubProject::find($this->project_id, $this->getOrganizationId());
    }

    /**
     * Get members for a specific project
     */
    public static function forProject(string $projectId, string $organizationId): Collection
    {
        return static::where('project_id', $projectId, $organizationId);
    }

    /**
     * Get projects for a specific user
     */
    public static function forUser(string $userId, string $organizationId): Collection
    {
        return static::where('user_id', $userId, $organizationId);
    }

    /**
     * Check if a user is a member of a project
     */
    public static function isMember(string $userId, string $projectId, string $organizationId): bool
    {
        return static::forProject($projectId, $organizationId)
            ->contains(fn($member) => $member->user_id === $userId);
    }

    /**
     * Add a user to a project
     */
    public static function addToProject(string $userId, string $projectId, string $organizationId, ?int $billableRate = null): static
    {
        // Check if already a member
        $existing = static::forProject($projectId, $organizationId)
            ->first(fn($member) => $member->user_id === $userId);

        if ($existing !== null) {
            return $existing;
        }

        return static::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'user_id' => $userId,
            'billable_rate' => $billableRate,
        ]);
    }

    /**
     * Remove a user from a project
     */
    public static function removeFromProject(string $userId, string $projectId, string $organizationId): bool
    {
        $member = static::forProject($projectId, $organizationId)
            ->first(fn($member) => $member->user_id === $userId);

        if ($member === null) {
            return false;
        }

        return $member->delete();
    }
}
