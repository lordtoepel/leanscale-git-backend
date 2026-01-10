<?php

declare(strict_types=1);

namespace App\Models\GitHub;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * GitHub-backed TimeEntry model.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $member_id
 * @property string|null $project_id
 * @property string|null $task_id
 * @property string $description
 * @property string $start
 * @property string|null $end
 * @property int|null $duration
 * @property bool $is_billable
 * @property int|null $billable_rate
 * @property array $tags
 * @property string $created_at
 * @property string $updated_at
 */
class GitHubTimeEntry extends GitHubModel
{
    protected static string $entityType = 'timelogs';
    protected static bool $organizationScoped = true;

    /**
     * Get the project for this time entry
     */
    public function project(): ?GitHubProject
    {
        if ($this->project_id === null) {
            return null;
        }

        return GitHubProject::find($this->project_id, $this->getOrganizationId());
    }

    /**
     * Get the task for this time entry
     */
    public function task(): ?GitHubTask
    {
        if ($this->task_id === null) {
            return null;
        }

        return GitHubTask::find($this->task_id, $this->getOrganizationId());
    }

    /**
     * Check if the time entry is currently running
     */
    public function isRunning(): bool
    {
        return $this->end === null;
    }

    /**
     * Stop the time entry
     */
    public function stop(): bool
    {
        if (!$this->isRunning()) {
            return false;
        }

        $start = Carbon::parse($this->start);
        $end = Carbon::now();

        $this->end = $end->toIso8601String();
        $this->duration = (int) $end->diffInSeconds($start);

        return $this->save();
    }

    /**
     * Calculate the duration in seconds
     */
    public function calculateDuration(): int
    {
        if ($this->duration !== null) {
            return $this->duration;
        }

        if ($this->start === null) {
            return 0;
        }

        $start = Carbon::parse($this->start);
        $end = $this->end !== null ? Carbon::parse($this->end) : Carbon::now();

        return (int) $end->diffInSeconds($start);
    }

    /**
     * Get the billable amount
     */
    public function getBillableAmount(): float
    {
        if (!($this->is_billable ?? false)) {
            return 0.0;
        }

        $rate = $this->billable_rate ?? 0;
        $hours = $this->calculateDuration() / 3600;

        return $hours * $rate;
    }

    /**
     * Get time entries for a specific date range
     */
    public static function forDateRange(
        string $organizationId,
        string $startDate,
        string $endDate,
        ?string $memberId = null
    ): Collection {
        $entries = static::all($organizationId);

        return $entries->filter(function (GitHubTimeEntry $entry) use ($startDate, $endDate, $memberId) {
            // Filter by member if specified
            if ($memberId !== null && $entry->member_id !== $memberId) {
                return false;
            }

            // Filter by date range
            $entryStart = Carbon::parse($entry->start);

            return $entryStart->gte(Carbon::parse($startDate)) &&
                   $entryStart->lte(Carbon::parse($endDate));
        })->values();
    }

    /**
     * Get running time entries
     */
    public static function running(string $organizationId, ?string $memberId = null): Collection
    {
        $entries = static::all($organizationId);

        return $entries->filter(function (GitHubTimeEntry $entry) use ($memberId) {
            if ($memberId !== null && $entry->member_id !== $memberId) {
                return false;
            }

            return $entry->isRunning();
        })->values();
    }

    /**
     * Get time entries for today
     */
    public static function today(string $organizationId, ?string $memberId = null): Collection
    {
        $today = Carbon::today();

        return static::forDateRange(
            $organizationId,
            $today->startOfDay()->toIso8601String(),
            $today->endOfDay()->toIso8601String(),
            $memberId
        );
    }

    /**
     * Get time entries for this week
     */
    public static function thisWeek(string $organizationId, ?string $memberId = null): Collection
    {
        $now = Carbon::now();

        return static::forDateRange(
            $organizationId,
            $now->startOfWeek()->toIso8601String(),
            $now->endOfWeek()->toIso8601String(),
            $memberId
        );
    }
}
