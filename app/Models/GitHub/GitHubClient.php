<?php

declare(strict_types=1);

namespace App\Models\GitHub;

use Illuminate\Support\Collection;

/**
 * GitHub-backed Client model.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $archived_at
 * @property string $created_at
 * @property string $updated_at
 */
class GitHubClient extends GitHubModel
{
    protected static string $entityType = 'clients';
    protected static bool $organizationScoped = true;

    /**
     * Check if the client is archived
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Archive the client
     */
    public function archive(): bool
    {
        $this->archived_at = now()->toIso8601String();

        return $this->save();
    }

    /**
     * Unarchive the client
     */
    public function unarchive(): bool
    {
        $this->archived_at = null;

        return $this->save();
    }

    /**
     * Get all projects for this client
     */
    public function projects(): Collection
    {
        return GitHubProject::where('client_id', $this->getKey(), $this->getOrganizationId());
    }

    /**
     * Get clients visible to a user (those with visible projects)
     */
    public static function visibleByEmployee(string $userId, string $organizationId): Collection
    {
        // Get all projects visible to the user
        $visibleProjects = GitHubProject::visibleByEmployee($userId, $organizationId);
        $clientIds = $visibleProjects->pluck('client_id')->filter()->unique();

        // Get all clients in the organization
        $allClients = static::all($organizationId);

        // Filter to only those with visible projects
        return $allClients->filter(function (GitHubClient $client) use ($clientIds) {
            return $clientIds->contains($client->getKey());
        })->values();
    }
}
