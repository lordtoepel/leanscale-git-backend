<?php

declare(strict_types=1);

namespace App\Models\GitHub;

use Illuminate\Support\Collection;

/**
 * GitHub-backed Project model.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $client_id
 * @property string $name
 * @property string $color
 * @property bool $is_billable
 * @property int|null $billable_rate
 * @property int|null $estimated_time
 * @property bool $is_archived
 * @property bool $is_public
 * @property string|null $archived_at
 * @property string $created_at
 * @property string $updated_at
 */
class GitHubProject extends GitHubModel
{
    protected static string $entityType = 'projects';
    protected static bool $organizationScoped = true;

    /**
     * Check if the project is archived
     */
    public function isArchived(): bool
    {
        return ($this->is_archived ?? false) || $this->archived_at !== null;
    }

    /**
     * Archive the project
     */
    public function archive(): bool
    {
        $this->is_archived = true;
        $this->archived_at = now()->toIso8601String();

        return $this->save();
    }

    /**
     * Unarchive the project
     */
    public function unarchive(): bool
    {
        $this->is_archived = false;
        $this->archived_at = null;

        return $this->save();
    }

    /**
     * Get the client for this project
     */
    public function client(): ?GitHubClient
    {
        if ($this->client_id === null) {
            return null;
        }

        return GitHubClient::find($this->client_id, $this->getOrganizationId());
    }

    /**
     * Get all tasks for this project
     */
    public function tasks(): Collection
    {
        return GitHubTask::where('project_id', $this->getKey(), $this->getOrganizationId());
    }

    /**
     * Get all time entries for this project
     */
    public function timeEntries(): Collection
    {
        return GitHubTimeEntry::where('project_id', $this->getKey(), $this->getOrganizationId());
    }

    /**
     * Calculate spent time from time entries
     */
    public function getSpentTime(): int
    {
        return $this->timeEntries()
            ->filter(fn(GitHubTimeEntry $entry) => $entry->end !== null)
            ->sum(fn(GitHubTimeEntry $entry) => $entry->duration ?? 0);
    }

    /**
     * Get projects visible to a user
     */
    public static function visibleByEmployee(string $userId, string $organizationId): Collection
    {
        $allProjects = static::all($organizationId);

        return $allProjects->filter(function (GitHubProject $project) use ($userId) {
            // Public projects are visible to all
            if ($project->is_public ?? false) {
                return true;
            }

            // Check if user is a member of the project
            $members = GitHubProjectMember::where('project_id', $project->getKey(), $project->getOrganizationId());

            return $members->contains(fn($member) => $member->user_id === $userId);
        })->values();
    }
}
