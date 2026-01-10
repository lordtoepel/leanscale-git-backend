<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles GitHub webhook events for real-time cache invalidation.
 *
 * When JSON files are edited directly in GitHub, this webhook
 * ensures the application cache is updated immediately.
 */
class GitHubWebhookController extends Controller
{
    /**
     * Handle incoming GitHub webhook
     */
    public function handle(Request $request): JsonResponse
    {
        // Verify webhook is enabled
        if (!config('github-data.webhook.enabled', false)) {
            return response()->json(['error' => 'Webhook disabled'], 403);
        }

        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            Log::warning('GitHub webhook signature verification failed');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->header('X-GitHub-Event');

        Log::info('GitHub webhook received', [
            'event' => $event,
            'delivery' => $request->header('X-GitHub-Delivery'),
        ]);

        return match ($event) {
            'push' => $this->handlePush($request),
            'ping' => $this->handlePing($request),
            default => response()->json(['message' => 'Event ignored']),
        };
    }

    /**
     * Handle push events - clear cache for modified files
     */
    private function handlePush(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Verify this is from the correct repository
        $expectedRepo = config('github-data.owner') . '/' . config('github-data.repo');
        $actualRepo = $payload['repository']['full_name'] ?? '';

        if ($actualRepo !== $expectedRepo) {
            Log::warning('GitHub webhook from unexpected repository', [
                'expected' => $expectedRepo,
                'actual' => $actualRepo,
            ]);

            return response()->json(['error' => 'Wrong repository'], 400);
        }

        // Get all modified files from all commits
        $modifiedFiles = collect();
        foreach ($payload['commits'] ?? [] as $commit) {
            $modifiedFiles = $modifiedFiles
                ->merge($commit['added'] ?? [])
                ->merge($commit['modified'] ?? [])
                ->merge($commit['removed'] ?? []);
        }

        $modifiedFiles = $modifiedFiles->unique();

        Log::info('GitHub webhook processing modified files', [
            'count' => $modifiedFiles->count(),
            'files' => $modifiedFiles->take(10)->toArray(),
        ]);

        // Clear cache for each modified path
        $clearedPaths = collect();
        foreach ($modifiedFiles as $file) {
            // Only process JSON files
            if (!Str::endsWith($file, '.json')) {
                continue;
            }

            // Skip schema files
            if (Str::startsWith($file, 'schemas/')) {
                continue;
            }

            // Extract the entity type and organization from the path
            $pathParts = explode('/', $file);
            $entityType = $pathParts[0] ?? null;

            if ($entityType === null) {
                continue;
            }

            // Handle different path structures:
            // - organizations/org-name.json -> organizations
            // - clients/{org-id}/client-name.json -> clients/{org-id}
            // - timelogs/{org-id}/2026-01/entry.json -> timelogs/{org-id}
            $cacheKey = $this->getCacheKeyForPath($file);

            if ($cacheKey !== null && !$clearedPaths->contains($cacheKey)) {
                Cache::forget($cacheKey);
                $clearedPaths->push($cacheKey);

                Log::debug('Cleared cache for path', ['key' => $cacheKey]);
            }
        }

        return response()->json([
            'message' => 'Cache cleared',
            'files_processed' => $modifiedFiles->count(),
            'cache_keys_cleared' => $clearedPaths->count(),
        ]);
    }

    /**
     * Handle ping events - verify webhook is configured
     */
    private function handlePing(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('GitHub webhook ping received', [
            'zen' => $payload['zen'] ?? 'No zen',
            'hook_id' => $payload['hook_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Pong!',
            'zen' => $payload['zen'] ?? null,
        ]);
    }

    /**
     * Verify the webhook signature from GitHub
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('github-data.webhook.secret');

        if (empty($secret)) {
            // If no secret is configured, allow the request (for development)
            Log::warning('GitHub webhook secret not configured');

            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (empty($signature)) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get the cache key for a file path
     */
    private function getCacheKeyForPath(string $path): ?string
    {
        $pathParts = explode('/', $path);

        if (count($pathParts) < 2) {
            return null;
        }

        $entityType = $pathParts[0];

        // Get entity config
        $entities = config('github-data.entities', []);
        if (!isset($entities[$entityType])) {
            return null;
        }

        $isScoped = $entities[$entityType]['scoped'] ?? true;

        if ($isScoped && count($pathParts) >= 2) {
            // Scoped entity - cache key includes organization
            $organizationId = $pathParts[1];

            return "github_data:{$entityType}/{$organizationId}";
        }

        // Non-scoped entity
        return "github_data:{$entityType}";
    }
}
