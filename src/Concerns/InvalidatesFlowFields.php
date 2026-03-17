<?php

namespace Openplain\FlowField\Concerns;

use Openplain\FlowField\Support\FlowFieldCache;

trait InvalidatesFlowFields
{
    public static function bootInvalidatesFlowFields(): void
    {
        static::created(function ($model) {
            $model->invalidateFlowFieldTargets();
        });

        static::updated(function ($model) {
            $model->invalidateFlowFieldTargetsOnUpdate();
        });

        static::deleted(function ($model) {
            $model->invalidateFlowFieldTargets();
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->invalidateFlowFieldTargets();
            });
        }
    }

    protected function invalidateFlowFieldTargets(): void
    {
        foreach ($this->flowFieldTargets as $targetClass => $foreignKey) {
            $parentId = $this->getAttribute($foreignKey);

            if ($parentId !== null) {
                $this->invalidateAndMaybeWarm($targetClass, $parentId);
            }
        }
    }

    protected function invalidateFlowFieldTargetsOnUpdate(): void
    {
        foreach ($this->flowFieldTargets as $targetClass => $foreignKey) {
            $parentId = $this->getAttribute($foreignKey);
            $foreignKeyChanged = $this->wasChanged($foreignKey);

            if (! $foreignKeyChanged && ! $this->hasRelevantChanges($targetClass)) {
                continue;
            }

            if ($parentId !== null) {
                $this->invalidateAndMaybeWarm($targetClass, $parentId);
            }

            if ($foreignKeyChanged) {
                $oldParentId = $this->getOriginal($foreignKey);

                if ($oldParentId !== null && $oldParentId !== $parentId) {
                    $this->invalidateAndMaybeWarm($targetClass, $oldParentId);
                }
            }
        }
    }

    protected function invalidateAndMaybeWarm(string $targetClass, int|string $parentId): void
    {
        FlowFieldCache::invalidateAll($targetClass, $parentId);

        if (config('flowfield.auto_warm', false)) {
            $parent = $targetClass::find($parentId);

            if ($parent) {
                FlowFieldCache::warm($parent);
            }
        }
    }

    protected function hasRelevantChanges(string $targetClass): bool
    {
        if (! in_array(HasFlowFields::class, class_uses_recursive($targetClass))) {
            return true;
        }

        $definitions = $targetClass::getFlowFieldDefinitions();
        $changedColumns = array_keys($this->getChanges());

        foreach ($definitions as $definition) {
            // For count/exists with no where conditions, an update can't change the result
            // (only inserts/deletes matter)
            if (in_array($definition->method, ['count', 'exists']) && empty($definition->where)) {
                continue;
            }

            $relevantColumns = $definition->getRelevantColumns();

            if (empty($relevantColumns)) {
                return true;
            }

            foreach ($relevantColumns as $column) {
                if (in_array($column, $changedColumns)) {
                    return true;
                }
            }
        }

        return false;
    }
}
