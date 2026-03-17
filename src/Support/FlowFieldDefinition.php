<?php

namespace Openplain\FlowField\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Openplain\FlowField\Attributes\FlowField;

class FlowFieldDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $method,
        public readonly string $relation,
        public readonly string $column,
        public readonly array $where,
        public readonly ?int $ttl,
        public readonly ?string $cacheKey,
    ) {}

    public static function fromAttribute(string $name, FlowField $attribute): static
    {
        return new static(
            name: $name,
            method: $attribute->method,
            relation: $attribute->relation,
            column: $attribute->column,
            where: $attribute->where,
            ttl: $attribute->ttl,
            cacheKey: $attribute->cacheKey,
        );
    }

    public function getCacheKeyName(): string
    {
        return $this->cacheKey ?? $this->name;
    }

    public function applyWhere(Builder|Relation $query): Builder|Relation
    {
        foreach ($this->where as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    public function getRelevantColumns(): array
    {
        $columns = [];

        if ($this->column !== '*') {
            $columns[] = $this->column;
        }

        foreach ($this->where as $col => $value) {
            $columns[] = $col;
        }

        return $columns;
    }
}
