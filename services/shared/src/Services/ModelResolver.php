<?php

namespace Shared\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Shared\Contracts\ModelResolverInterface;

/**
 * Model Resolver Service
 * 
 * Resolves Eloquent models from consuming services using Laravel's service container.
 * This allows the shared library to work with models without direct imports,
 * preventing circular dependencies.
 */
class ModelResolver implements ModelResolverInterface
{
    /**
     * Model mappings registered by consuming services
     *
     * @var array
     */
    protected array $modelMappings = [];

    /**
     * Register a model mapping
     *
     * @param string $modelName
     * @param string $modelClass
     * @return void
     */
    public function registerModel(string $modelName, string $modelClass): void
    {
        $this->modelMappings[$modelName] = $modelClass;
    }

    /**
     * Register multiple model mappings
     *
     * @param array $mappings
     * @return void
     */
    public function registerModels(array $mappings): void
    {
        $this->modelMappings = array_merge($this->modelMappings, $mappings);
    }

    /**
     * Resolve a model class by name
     *
     * @param string $modelName
     * @return string|null
     */
    public function resolveModel(string $modelName): ?string
    {
        return $this->modelMappings[$modelName] ?? null;
    }

    /**
     * Get a model instance by name
     *
     * @param string $modelName
     * @return Model|null
     */
    public function getModel(string $modelName): ?Model
    {
        $modelClass = $this->resolveModel($modelName);
        
        if (!$modelClass || !class_exists($modelClass)) {
            return null;
        }

        return App::make($modelClass);
    }

    /**
     * Check if a model is available
     *
     * @param string $modelName
     * @return bool
     */
    public function hasModel(string $modelName): bool
    {
        $modelClass = $this->resolveModel($modelName);
        return $modelClass && class_exists($modelClass);
    }

    /**
     * Get all available models
     *
     * @return array
     */
    public function getAvailableModels(): array
    {
        return array_keys($this->modelMappings);
    }

    /**
     * Create a query builder for a model
     *
     * @param string $modelName
     * @return \Illuminate\Database\Eloquent\Builder|null
     */
    public function query(string $modelName): ?\Illuminate\Database\Eloquent\Builder
    {
        $model = $this->getModel($modelName);
        return $model ? $model->newQuery() : null;
    }
}
