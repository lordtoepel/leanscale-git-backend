<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * GitHub-based data provider that reads/writes JSON files from a GitHub repository.
 * Replaces traditional database operations with git commits.
 */
class GitHubDataProvider
{
    private string $owner;
    private string $repo;
    private string $branch;
    private string $token;
    private string $baseUrl = 'https://api.github.com';

    public function __construct()
    {
        $this->owner = config('github-data.owner', 'lordtoepel');
        $this->repo = config('github-data.repo', 'leanscale-data');
        $this->branch = config('github-data.branch', 'main');
        $this->token = config('github-data.token', env('GITHUB_DATA_TOKEN'));
    }

    /**
     * Get all entities of a specific type for an organization
     */
    public function getAll(string $entityType, ?string $organizationId = null): Collection
    {
        $path = $organizationId
            ? "{$entityType}/{$organizationId}"
            : $entityType;

        $cacheKey = "github_data:{$path}";

        return Cache::remember($cacheKey, 60, function () use ($path) {
            $contents = $this->getDirectoryContents($path);

            return collect($contents)
                ->filter(fn($item) => Str::endsWith($item['name'], '.json'))
                ->map(fn($item) => $this->getFileContent($item['path']))
                ->filter()
                ->values();
        });
    }

    /**
     * Get a single entity by ID
     */
    public function find(string $entityType, string $id, ?string $organizationId = null): ?array
    {
        $files = $this->getAll($entityType, $organizationId);

        return $files->firstWhere('id', $id);
    }

    /**
     * Create a new entity
     */
    public function create(string $entityType, array $data, ?string $organizationId = null): array
    {
        $id = $data['id'] ?? (string) Str::uuid();
        $data['id'] = $id;
        $data['created_at'] = $data['created_at'] ?? now()->toIso8601String();
        $data['updated_at'] = now()->toIso8601String();

        $filename = $this->generateFilename($entityType, $data);
        $path = $organizationId
            ? "{$entityType}/{$organizationId}/{$filename}"
            : "{$entityType}/{$filename}";

        $entityName = $data['name'] ?? $id;
        $this->createOrUpdateFile($path, $data, "Create {$entityType}: {$entityName}");

        $this->clearCache($entityType, $organizationId);

        return $data;
    }

    /**
     * Update an existing entity
     */
    public function update(string $entityType, string $id, array $data, ?string $organizationId = null): ?array
    {
        $existing = $this->find($entityType, $id, $organizationId);

        if (!$existing) {
            return null;
        }

        $updated = array_merge($existing, $data);
        $updated['updated_at'] = now()->toIso8601String();

        // Find the file path
        $files = $this->getDirectoryContents($organizationId
            ? "{$entityType}/{$organizationId}"
            : $entityType);

        $file = collect($files)->first(function ($item) use ($id) {
            if (!Str::endsWith($item['name'], '.json')) {
                return false;
            }
            $content = $this->getFileContent($item['path']);
            return $content && ($content['id'] ?? null) === $id;
        });

        if (!$file) {
            return null;
        }

        $entityName = $updated['name'] ?? $id;
        $this->createOrUpdateFile(
            $file['path'],
            $updated,
            "Update {$entityType}: {$entityName}",
            $file['sha']
        );

        $this->clearCache($entityType, $organizationId);

        return $updated;
    }

    /**
     * Delete an entity
     */
    public function delete(string $entityType, string $id, ?string $organizationId = null): bool
    {
        $path = $organizationId
            ? "{$entityType}/{$organizationId}"
            : $entityType;

        $files = $this->getDirectoryContents($path);

        $file = collect($files)->first(function ($item) use ($id) {
            if (!Str::endsWith($item['name'], '.json')) {
                return false;
            }
            $content = $this->getFileContent($item['path']);
            return $content && ($content['id'] ?? null) === $id;
        });

        if (!$file) {
            return false;
        }

        $this->deleteFile($file['path'], $file['sha'], "Delete {$entityType}: {$id}");

        $this->clearCache($entityType, $organizationId);

        return true;
    }

    /**
     * Query entities with filters
     */
    public function query(string $entityType, array $filters = [], ?string $organizationId = null): Collection
    {
        $all = $this->getAll($entityType, $organizationId);

        foreach ($filters as $key => $value) {
            $all = $all->where($key, $value);
        }

        return $all->values();
    }

    /**
     * Get directory contents from GitHub
     */
    private function getDirectoryContents(string $path): array
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/repos/{$this->owner}/{$this->repo}/contents/{$path}", [
                'ref' => $this->branch
            ]);

        if (!$response->successful()) {
            return [];
        }

        $contents = $response->json();

        // If it's a single file, wrap in array
        if (isset($contents['name'])) {
            return [$contents];
        }

        return $contents;
    }

    /**
     * Get and parse JSON file content
     */
    private function getFileContent(string $path): ?array
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/repos/{$this->owner}/{$this->repo}/contents/{$path}", [
                'ref' => $this->branch
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        if (!isset($data['content'])) {
            return null;
        }

        $content = base64_decode($data['content']);

        return json_decode($content, true);
    }

    /**
     * Create or update a file in the repository
     */
    private function createOrUpdateFile(string $path, array $content, string $message, ?string $sha = null): void
    {
        $payload = [
            'message' => $message,
            'content' => base64_encode(json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            'branch' => $this->branch,
        ];

        if ($sha) {
            $payload['sha'] = $sha;
        }

        Http::withToken($this->token)
            ->put("{$this->baseUrl}/repos/{$this->owner}/{$this->repo}/contents/{$path}", $payload);
    }

    /**
     * Delete a file from the repository
     */
    private function deleteFile(string $path, string $sha, string $message): void
    {
        Http::withToken($this->token)
            ->delete("{$this->baseUrl}/repos/{$this->owner}/{$this->repo}/contents/{$path}", [
                'message' => $message,
                'sha' => $sha,
                'branch' => $this->branch,
            ]);
    }

    /**
     * Generate a filename for an entity
     */
    private function generateFilename(string $entityType, array $data): string
    {
        $name = $data['name'] ?? $data['id'];
        $slug = Str::slug($name);

        return "{$entityType}-{$slug}.json";
    }

    /**
     * Clear cache for an entity type
     */
    private function clearCache(string $entityType, ?string $organizationId = null): void
    {
        $path = $organizationId
            ? "{$entityType}/{$organizationId}"
            : $entityType;

        Cache::forget("github_data:{$path}");
    }

    /**
     * Force refresh cache for an entity type
     */
    public function refresh(string $entityType, ?string $organizationId = null): Collection
    {
        $this->clearCache($entityType, $organizationId);
        return $this->getAll($entityType, $organizationId);
    }
}
