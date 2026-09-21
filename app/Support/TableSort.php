<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TableSort
{
    /** Apply only developer-defined columns/subqueries, never a request-supplied SQL identifier. */
    public static function apply(Builder $query, Request $request, array $columns, string $prefix = 'table'): Builder
    {
        $key = $request->input($prefix.'_sort');
        if (! is_string($key) || ! array_key_exists($key, $columns)) {
            return $query;
        }
        $direction = $request->input($prefix.'_direction') === 'desc' ? 'desc' : 'asc';

        return $query->reorder()->orderBy($columns[$key], $direction)->orderBy($query->getModel()->qualifyColumn('id'));
    }
}
