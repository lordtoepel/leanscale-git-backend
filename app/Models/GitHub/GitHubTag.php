<?php

declare(strict_types=1);

namespace App\Models\GitHub;

use Illuminate\Support\Collection;

/**
 * GitHub-backed Tag model.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $created_at
 * @property string $updated_at
 */
class GitHubTag extends GitHubModel
{
    protected static string $entityType = 'tags';
    protected static bool $organizationScoped = true;

    /**
     * Find or create a tag by name
     */
    public static function findOrCreateByName(string $name, string $organizationId): static
    {
        $existing = static::where('name', $name, $organizationId)->first();

        if ($existing !== null) {
            return $existing;
        }

        return static::create([
            'organization_id' => $organizationId,
            'name' => $name,
        ]);
    }

    /**
     * Get all time entries with this tag
     */
    public function timeEntries(): Collection
    {
        $allEntries = GitHubTimeEntry::all($this->getOrganizationId());

        return $allEntries->filter(function (GitHubTimeEntry $entry) {
            $tags = $entry->tags ?? [];

            return in_array($this->getKey(), $tags);
        })->values();
    }
}
