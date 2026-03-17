<?php

namespace Openplain\FlowField\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class FlowFieldCalculator
{
    public static function calculate(Model $parent, FlowFieldDefinition $definition): mixed
    {
        $query = $parent->{$definition->relation}();

        $definition->applyWhere($query);

        return match ($definition->method) {
            'sum' => $query->sum($definition->column),
            'count' => $query->count($definition->column),
            'avg' => $query->avg($definition->column),
            'min' => $query->min($definition->column),
            'max' => $query->max($definition->column),
            'exists' => $query->exists(),
            default => throw new InvalidArgumentException("Unsupported FlowField method: {$definition->method}"),
        };
    }
}
