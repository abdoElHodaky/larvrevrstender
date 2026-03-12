<?php

namespace Shared\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Resolver Interface
 * 
 * Provides a contract for resolving Eloquent models from consuming services
 * without creating circular dependencies in the shared library.
 */
interface ModelResolverInterface
{
    /**
     * Resolve a model class by name
     *
     * @param string $modelName
     * @return string|null The fully qualified model class name
     */
    public function resolveModel(string $modelName): ?string;

    /**
     * Get a model instance by name
     *
     * @param string $modelName
     * @return Model|null
     */
    public function getModel(string $modelName): ?Model;

    /**
     * Check if a model is available
     *
     * @param string $modelName
     * @return bool
     */
    public function hasModel(string $modelName): bool;

    /**
     * Get all available models
     *
     * @return array
     */
    public function getAvailableModels(): array;
}
