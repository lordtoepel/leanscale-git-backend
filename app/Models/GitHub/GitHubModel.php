<?php

declare(strict_types=1);

namespace App\Models\GitHub;

use App\Services\GitHubDataProvider;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * Base model for GitHub-backed data storage.
 * Provides an Eloquent-like interface for entities stored as JSON in GitHub.
 */
abstract class GitHubModel implements Arrayable, Jsonable, JsonSerializable
{
    protected static string $entityType;
    protected static bool $organizationScoped = true;

    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    protected static ?GitHubDataProvider $provider = null;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->original = $this->attributes;
    }

    /**
     * Get the data provider instance
     */
    protected static function getProvider(): GitHubDataProvider
    {
        if (static::$provider === null) {
            static::$provider = app(GitHubDataProvider::class);
        }

        return static::$provider;
    }

    /**
     * Fill the model with attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Set an attribute value
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get an attribute value
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Magic getter for attributes
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter for attributes
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Check if attribute exists
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Get the model's ID
     */
    public function getKey(): ?string
    {
        return $this->getAttribute('id');
    }

    /**
     * Get the organization ID for scoped queries
     */
    public function getOrganizationId(): ?string
    {
        return $this->getAttribute('organization_id');
    }

    /**
     * Find a model by its ID
     */
    public static function find(string $id, ?string $organizationId = null): ?static
    {
        $data = static::getProvider()->find(static::$entityType, $id, $organizationId);

        if ($data === null) {
            return null;
        }

        $model = new static($data);
        $model->exists = true;
        $model->original = $model->attributes;

        return $model;
    }

    /**
     * Find a model or throw an exception
     */
    public static function findOrFail(string $id, ?string $organizationId = null): static
    {
        $model = static::find($id, $organizationId);

        if ($model === null) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                sprintf('No query results for model [%s] %s', static::class, $id)
            );
        }

        return $model;
    }

    /**
     * Get all models
     */
    public static function all(?string $organizationId = null): Collection
    {
        $items = static::getProvider()->getAll(static::$entityType, $organizationId);

        return $items->map(function (array $data) {
            $model = new static($data);
            $model->exists = true;
            $model->original = $model->attributes;

            return $model;
        });
    }

    /**
     * Query with filters
     */
    public static function where(string|array $column, mixed $value = null, ?string $organizationId = null): Collection
    {
        $filters = is_array($column) ? $column : [$column => $value];

        $items = static::getProvider()->query(static::$entityType, $filters, $organizationId);

        return $items->map(function (array $data) {
            $model = new static($data);
            $model->exists = true;
            $model->original = $model->attributes;

            return $model;
        });
    }

    /**
     * Create a new model instance
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * Save the model
     */
    public function save(): bool
    {
        $organizationId = static::$organizationScoped ? $this->getOrganizationId() : null;

        if ($this->exists) {
            // Update
            $result = static::getProvider()->update(
                static::$entityType,
                $this->getKey(),
                $this->attributes,
                $organizationId
            );

            if ($result !== null) {
                $this->fill($result);
                $this->original = $this->attributes;

                return true;
            }

            return false;
        } else {
            // Create
            if (!isset($this->attributes['id'])) {
                $this->attributes['id'] = (string) Str::uuid();
            }

            $result = static::getProvider()->create(
                static::$entityType,
                $this->attributes,
                $organizationId
            );

            $this->fill($result);
            $this->original = $this->attributes;
            $this->exists = true;

            return true;
        }
    }

    /**
     * Delete the model
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $organizationId = static::$organizationScoped ? $this->getOrganizationId() : null;

        $result = static::getProvider()->delete(
            static::$entityType,
            $this->getKey(),
            $organizationId
        );

        if ($result) {
            $this->exists = false;
        }

        return $result;
    }

    /**
     * Update the model with new attributes
     */
    public function update(array $attributes): bool
    {
        $this->fill($attributes);

        return $this->save();
    }

    /**
     * Get the changed attributes
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Check if the model has been modified
     */
    public function isDirty(string|array|null $attributes = null): bool
    {
        $dirty = $this->getDirty();

        if ($attributes === null) {
            return count($dirty) > 0;
        }

        foreach ((array) $attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refresh the model from storage
     */
    public function refresh(): static
    {
        if (!$this->exists) {
            return $this;
        }

        $organizationId = static::$organizationScoped ? $this->getOrganizationId() : null;
        $fresh = static::find($this->getKey(), $organizationId);

        if ($fresh !== null) {
            $this->attributes = $fresh->attributes;
            $this->original = $this->attributes;
        }

        return $this;
    }

    /**
     * Convert model to array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert model to JSON
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Prepare for JSON serialization
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert to string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
