<?php

declare(strict_types=1);

namespace App\Models\GitHub;

use Illuminate\Support\Collection;

/**
 * GitHub-backed Task model.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $project_id
 * @property string $name
 * @property bool $is_done
 * @property int|null $estimated_time
 * @property string $created_at
 * @property string $updated_at
 */
class GitHubTask extends GitHubModel
{
    protected static string $entityType = 'tasks';
    protected static bool $organizationScoped = true;

    /**
     * Mark the task as done
     */
    public function markAsDone(): bool
    {
        $this->is_done = true;

        return $this->save();
    }

    /**
     * Mark the task as not done
     */
    public function markAsNotDone(): bool
    {
        $this->is_done = false;

        return $this->save();
    }

    /**
     * Get the project for this task
     */
    public function project(): ?GitHubProject
    {
        if ($this->project_id === null) {
            return null;
        }

        return GitHubProject::find($this->project_id, $this->getOrganizationId());
    }

    /**
     * Get all time entries for this task
     */
    public function timeEntries(): Collection
    {
        return GitHubTimeEntry::where('task_id', $this->getKey(), $this->getOrganizationId());
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
     * Get tasks for a specific project
     */
    public static function forProject(string $projectId, string $organizationId): Collection
    {
        return static::where('project_id', $projectId, $organizationId);
    }

    /**
     * Get incomplete tasks
     */
    public static function incomplete(string $organizationId): Collection
    {
        return static::all($organizationId)->filter(fn(GitHubTask $task) => !($task->is_done ?? false));
    }

    /**
     * Get completed tasks
     */
    public static function completed(string $organizationId): Collection
    {
        return static::all($organizationId)->filter(fn(GitHubTask $task) => $task->is_done ?? false);
    }
}
